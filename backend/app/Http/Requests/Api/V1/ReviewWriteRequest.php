<?php

namespace App\Http\Requests\Api\V1;

use App\Data\ReviewInput;
use App\Models\Game;
use App\Models\GameReview;
use App\Models\RatingCategory;
use App\Models\User;
use App\Services\GameService;
use App\Services\ReviewService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 리뷰 작성·수정 (api-spec §7.3, 리뷰 정책 §4). 웹과 앱이 같이 쓴다.
 *
 * 작성(라우트에 {game})은 검증 전에 작성 자격을 확인해서, 실패 순서가 api-spec §7.3과 같게 한다
 * (GAME_NOT_RELEASED → REVIEW_HIDDEN_EXISTS → REVIEW_ALREADY_EXISTS → VALIDATION_FAILED).
 * 수정(라우트에 {review})은 리뷰의 게임 기준으로 플랫폼을 검증한다. 본인·상태 확인은 서비스가 한다.
 */
class ReviewWriteRequest extends FormRequest
{
    private ?Game $targetGame = null;

    public function authorize(): bool
    {
        $game = $this->game();
        if ($this->route('review') === null) {
            /** @var User $user */
            $user = $this->user();
            app(ReviewService::class)->assertCanWrite($user, $game);
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $slugs = RatingCategory::query()->active()->pluck('slug')->all();

        return [
            /** 제목 2~50자 */
            'title' => ['required', 'string', 'min:2', 'max:50'],
            /** 본문 50~5,000자 */
            'content' => ['required', 'string', 'min:50', 'max:5000'],
            /** 플레이한 플랫폼 ID. 이 게임이 출시된 플랫폼 중 하나 */
            'platform_id' => ['required', 'integer', Rule::in(app(ReviewService::class)->releasedPlatformIds($this->game()))],
            /** 종합 점수 1~10 */
            'overall_score' => ['required', 'integer', 'between:1,10'],
            /** 세부 점수 `{slug: score}` (선택). 키는 `GET /rating-categories`의 slug, 값은 1~10. null이면 입력하지 않은 것으로 본다 */
            'ratings' => ['sometimes', 'nullable', 'array:'.implode(',', $slugs)],
            'ratings.*' => ['nullable', 'integer', 'between:1,10'],
            /** 스포일러 포함 */
            'has_spoiler' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => '제목',
            'content' => '본문',
            'platform_id' => '플레이한 플랫폼',
            'overall_score' => '종합 점수',
            'ratings' => '세부 점수',
            'ratings.*' => '세부 점수',
            'has_spoiler' => '스포일러 포함',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'platform_id.in' => '이 게임이 출시된 플랫폼 중에서 골라 주세요.',
            'ratings.array' => '알 수 없는 평가 항목이 있습니다.',
        ];
    }

    public function reviewInput(): ReviewInput
    {
        /** @var array<string, int|null> $ratings */
        $ratings = $this->validated('ratings') ?? [];

        return new ReviewInput(
            title: trim((string) $this->validated('title')),
            content: trim((string) $this->validated('content')),
            platformId: (int) $this->validated('platform_id'),
            overallScore: (int) $this->validated('overall_score'),
            ratings: array_map(intval(...), array_filter($ratings, fn ($score) => $score !== null)),
            hasSpoiler: $this->boolean('has_spoiler'),
        );
    }

    /**
     * 대상 게임. 작성은 {game}(공개 게임만), 수정은 {review}의 게임.
     */
    public function game(): Game
    {
        if ($this->targetGame !== null) {
            return $this->targetGame;
        }

        $review = $this->route('review');
        if ($review !== null) {
            $reviewModel = $review instanceof GameReview ? $review : app(ReviewService::class)->find((int) $review);

            return $this->targetGame = $reviewModel->game;
        }

        return $this->targetGame = app(GameService::class)->findPublished((int) $this->route('game'));
    }
}
