<?php

namespace Tests\Feature;

use App\Enums\CommentStatus;
use App\Enums\ReviewStatus;
use App\Filament\Resources\Boards\Pages\ManageBoards;
use App\Models\Board;
use App\Models\PointTransaction;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\PostComment;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 커뮤니티 (REQ-401~408, 화면 설계 W-09~W-12, 포인트 정책 §3·§5)
 */
class CommunityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Board $free;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
        $this->user = User::factory()->create();
        $this->free = Board::query()->where('slug', 'free')->sole();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return [
            'board_id' => $this->free->id,
            'title' => '오늘 해 본 게임',
            'content' => '주말에 새로 나온 게임을 해 봤는데 생각보다 재미있었어요.',
            ...$override,
        ];
    }

    public function test_boards_and_lists_are_public(): void
    {
        $old = Post::factory()->create(['title' => '오래된 글', 'created_at' => now()->subDay(), 'like_count' => 5]);
        $new = Post::factory()->create(['title' => '새 글 검색어']);
        Post::factory()->create(['status' => ReviewStatus::Hidden]);
        Post::factory()->create(['board_id' => Board::query()->where('slug', 'qna')->value('id')]);

        $this->get('/community')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('community/Index')
            ->has('boards', 5)
            ->has('posts', 3));

        $this->get('/community/free')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('community/Board')
            ->where('posts.pagination.total', 2)
            ->where('posts.items.0.id', $new->id)
            ->where('filters', ['sort' => 'latest', 'q' => '']));
        $this->get('/community/free?sort=likes')->assertInertia(fn (Assert $page) => $page->where('posts.items.0.id', $old->id));
        $this->get('/community/free?q=검색어')->assertInertia(fn (Assert $page) => $page->where('posts.pagination.total', 1));
        $this->get('/community/free?q=검')->assertInertia(fn (Assert $page) => $page
            ->where('keywordError', '2자 이상 입력해 주세요.')
            ->where('posts.pagination.total', 2));
        $this->get('/community/free?q=100%')->assertInertia(fn (Assert $page) => $page->where('posts.pagination.total', 0));

        $this->free->update(['is_active' => false]);
        $this->get('/community/free')->assertNotFound();
        $this->get("/posts/{$new->id}")->assertNotFound();
    }

    public function test_write_with_images_grants_points(): void
    {
        $this->actingAs($this->user);
        $this->get('/community/free/write')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('community/Form')
            ->where('board.slug', 'free')
            ->where('limits.max_images', 10));

        $response = $this->post('/posts', $this->payload([
            'images' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.jpg')],
        ]));
        $post = Post::query()->sole();
        $response->assertRedirect("/posts/{$post->id}")->assertInertiaFlash('toast.message', '글을 등록했습니다. 20P를 받았습니다.');

        $paths = PostAttachment::query()->orderBy('sort_order')->pluck('file_path');
        $this->assertCount(2, $paths);
        $this->assertStringStartsWith("posts/{$post->id}/", $paths[0]);
        Storage::assertExists($paths->all());
        $this->assertSame("post_create:{$post->id}", PointTransaction::query()->sole()->idempotency_key);

        // 본문이 20자 미만이면 글은 등록되지만 포인트는 없다
        $this->post('/posts', $this->payload(['content' => '짧은 글']))->assertInertiaFlash('toast.message', '글을 등록했습니다.');
        $this->assertSame(20, $this->user->refresh()->point_balance);
    }

    public function test_validation_and_image_limits(): void
    {
        $this->actingAs($this->user);

        $this->post('/posts', ['board_id' => 999, 'title' => 'a', 'content' => ''])
            ->assertSessionHasErrors(['board_id', 'title', 'content']);
        $this->post('/posts', $this->payload(['images' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]]))
            ->assertSessionHasErrors(['images.0' => 'jpg, png, webp, gif 이미지만 올릴 수 있습니다.']);
        $this->post('/posts', $this->payload(['images' => [UploadedFile::fake()->image('big.png')->size(10 * 1024 + 1)]]))
            ->assertSessionHasErrors('images.0');
        $this->post('/posts', $this->payload(['images' => array_map(fn ($i) => UploadedFile::fake()->image("{$i}.png"), range(1, 11))]))
            ->assertSessionHasErrors('images');
        $this->assertSame(0, Post::query()->count());
    }

    public function test_edit_replaces_images_within_limit_and_delete(): void
    {
        $this->actingAs($this->user);
        $this->post('/posts', $this->payload(['images' => array_map(fn ($i) => UploadedFile::fake()->image("{$i}.png"), range(1, 9))]));
        $post = Post::query()->sole();
        $first = PostAttachment::query()->orderBy('id')->first();

        // 9개 + 새 2개 = 11개라 거부
        $this->put("/posts/{$post->id}", ['title' => '수정', 'content' => '본문', 'images' => [UploadedFile::fake()->image('x.png'), UploadedFile::fake()->image('y.png')]])
            ->assertSessionHasErrors('images');

        // 1개 지우고 2개 더하면 10개
        $this->put("/posts/{$post->id}", [
            'title' => '고친 제목',
            'content' => '고친 본문',
            'remove_attachment_ids' => [$first->id],
            'images' => [UploadedFile::fake()->image('x.png'), UploadedFile::fake()->image('y.png')],
        ])->assertRedirect("/posts/{$post->id}");
        $this->assertSame(10, $post->attachments()->count());
        Storage::assertMissing($first->file_path);
        $this->assertSame('고친 제목', $post->refresh()->title);
        $this->assertNotNull($post->edited_at);

        $this->actingAs(User::factory()->create())
            ->from("/posts/{$post->id}")
            ->delete("/posts/{$post->id}")
            ->assertInertiaFlash('toast.message', '본인 글만 수정·삭제할 수 있습니다.');

        $this->actingAs($this->user)->delete("/posts/{$post->id}")->assertRedirect('/community/free');
        $this->assertSame(ReviewStatus::Deleted, $post->refresh()->status);
        $this->get("/posts/{$post->id}")->assertNotFound();
    }

    public function test_detail_counts_views_once_per_session(): void
    {
        $post = Post::factory()->create(['title' => '조회수 글']);

        $this->get("/posts/{$post->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('community/Show')
            ->where('post.title', '조회수 글')
            ->where('post.view_count', 1)
            ->where('post.is_mine', null)
            ->where('meta.url', route('posts.show', $post->id)));
        $this->get("/posts/{$post->id}");
        $this->assertSame(1, $post->refresh()->view_count);

        $post->update(['status' => ReviewStatus::Blinded]);
        $this->get("/posts/{$post->id}")->assertNotFound();
        $this->actingAs($post->user)->get("/posts/{$post->id}")->assertInertia(fn (Assert $page) => $page
            ->where('post.status', 'blinded')
            ->where('comments', null));
    }

    public function test_like_and_comments(): void
    {
        $post = Post::factory()->create();
        $this->actingAs($this->user);
        $from = "/posts/{$post->id}";

        $this->from($from)->post("/posts/{$post->id}/like")->assertRedirect($from);
        $this->from($from)->post("/posts/{$post->id}/like");
        $this->assertSame(1, $post->refresh()->like_count);
        $this->from($from)->delete("/posts/{$post->id}/like");
        $this->assertSame(0, $post->refresh()->like_count);
        $this->assertSame(0, $post->user->refresh()->point_balance, '게시글 추천은 포인트가 없다');

        $this->from($from)->post("/posts/{$post->id}/comments", ['content' => '좋은 글이네요'])
            ->assertInertiaFlash('toast.message', '댓글을 등록했습니다. 5P를 받았습니다.');
        $comment = PostComment::query()->sole();
        $this->assertSame("comment_create:post:{$comment->id}", PointTransaction::query()->where('user_id', $this->user->id)->sole()->idempotency_key);
        $this->assertSame(1, $post->refresh()->comment_count);

        $this->from($from)->put("/post-comments/{$comment->id}", ['content' => '고친 댓글']);
        $this->assertSame('고친 댓글', $comment->refresh()->content);
        $this->from($from)->delete("/post-comments/{$comment->id}");
        $this->assertSame(CommentStatus::Deleted, $comment->refresh()->status);
        $this->assertSame(0, $post->refresh()->comment_count);

        $this->get($from)->assertInertia(fn (Assert $page) => $page
            ->where('comments.0.content', null)
            ->where('comments.0.placeholder', '삭제된 댓글입니다.'));

        $this->actingAs($post->user)->from($from)->post("/posts/{$post->id}/like")
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => '본인 글은 추천할 수 없습니다.']);
    }

    public function test_guest_cannot_write(): void
    {
        $this->get('/community/free/write')->assertRedirect('/login');
        $this->post('/posts', $this->payload())->assertRedirect('/login');
    }

    public function test_admin_manages_boards(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', Role::ADMIN)->value('id'));
        $moderator = User::factory()->create();
        $moderator->roles()->attach(Role::query()->where('slug', Role::MODERATOR)->value('id'));

        $this->actingAs($moderator)->get('/admin/boards')->assertForbidden();
        $this->actingAs($admin)->get('/admin/boards')->assertOk()->assertSee('자유게시판');

        Post::factory()->create();
        Livewire::test(ManageBoards::class)
            ->callAction('create', data: ['name' => '스크린샷', 'slug' => 'screenshots', 'is_active' => true])
            ->assertHasNoActionErrors()
            ->assertTableActionHidden('delete', $this->free)
            ->assertTableActionVisible('delete', Board::query()->where('slug', 'screenshots')->sole());
        $this->get('/community/screenshots')->assertOk();
    }
}
