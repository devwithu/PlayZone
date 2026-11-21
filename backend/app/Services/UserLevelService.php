<?php

namespace App\Services;

use App\Enums\LevelChangeReason;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\UserLevelHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 회원 등급 (포인트 정책 §9, ERD §8). 등급은 누적 획득 포인트(lifetime_points)로 정한다.
 *
 * - 포인트 적립·회수: PointService가 같은 트랜잭션에서 sync()를 부른다.
 * - 관리자가 구간을 바꾸면: recalculateAll()로 전체 회원을 다시 계산한다(탈퇴 회원 제외).
 * - 등급을 지우면: 그 등급 회원을 바로 아래 등급으로 옮긴 뒤 지운다. 기본 등급(0P)은 지울 수 없다.
 * 바뀐 회원은 user_level_histories에 남긴다. 등급 변경 알림은 4단계 알림 기능에서 붙인다.
 */
class UserLevelService
{
    /**
     * 누적 포인트에 맞는 등급. 기본 등급(0P)이 있으므로 항상 하나가 있다.
     */
    public function levelFor(int $lifetimePoints): UserLevel
    {
        return UserLevel::query()
            ->where('min_points', '<=', $lifetimePoints)
            ->orderByDesc('min_points')
            ->firstOrFail();
    }

    /**
     * 다음 등급 (가장 높은 등급이면 null).
     */
    public function nextLevel(int $lifetimePoints): ?UserLevel
    {
        return UserLevel::query()
            ->where('min_points', '>', $lifetimePoints)
            ->orderBy('min_points')
            ->first();
    }

    /**
     * 화면·API용 등급 요약 (W-17 "골드까지 600P"). 탈퇴 회원 등 등급이 비어 있으면 누적 포인트로 계산한다.
     *
     * @return array{level: array{id: int, name: string, min_points: int, icon_url: string|null}, next_level: array{id: int, name: string, min_points: int, points_needed: int}|null}
     */
    public function summary(User $user): array
    {
        $level = $user->level ?? $this->levelFor($user->lifetime_points);
        $next = $this->nextLevel($user->lifetime_points);

        return [
            'level' => [
                'id' => $level->id,
                'name' => $level->name,
                'min_points' => $level->min_points,
                'icon_url' => $level->iconUrl(),
            ],
            'next_level' => $next === null ? null : [
                'id' => $next->id,
                'name' => $next->name,
                'min_points' => $next->min_points,
                'points_needed' => $next->min_points - $user->lifetime_points,
            ],
        ];
    }

    /**
     * 회원 등급을 누적 포인트에 맞춘다. 바뀌었으면 이력을 남기고 true. $user는 최신 값이어야 한다(잠긴 행 권장).
     */
    public function sync(User $user, LevelChangeReason $reason): bool
    {
        $level = $this->levelFor($user->lifetime_points);

        if ($user->user_level_id === $level->id) {
            return false;
        }

        UserLevelHistory::query()->create([
            'user_id' => $user->id,
            'old_level_id' => $user->user_level_id,
            'new_level_id' => $level->id,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        $user->user_level_id = $level->id;
        $user->save();

        return true;
    }

    /**
     * 관리자가 구간을 바꾼 뒤 전체 회원 등급을 다시 계산한다. 바뀐 회원 수를 돌려준다.
     */
    public function recalculateAll(): int
    {
        return DB::transaction(function () {
            // 등급 표를 잠가 계산 중에 구간이 또 바뀌지 않게 한다
            DB::statement('LOCK TABLE user_levels IN SHARE MODE');

            $target = '(SELECT l.id FROM user_levels l WHERE l.min_points <= u.lifetime_points ORDER BY l.min_points DESC LIMIT 1)';
            $changed = "FROM users u WHERE u.status <> ? AND u.user_level_id IS DISTINCT FROM {$target}";
            $withdrawn = UserStatus::Withdrawn->value;

            DB::insert(
                "INSERT INTO user_level_histories (user_id, old_level_id, new_level_id, reason, created_at) SELECT u.id, u.user_level_id, {$target}, ?, ? {$changed}",
                [LevelChangeReason::Admin->value, now(), $withdrawn],
            );

            return DB::update(
                "UPDATE users AS u SET user_level_id = {$target}, updated_at = ? WHERE u.status <> ? AND u.user_level_id IS DISTINCT FROM {$target}",
                [now(), $withdrawn],
            );
        });
    }

    /**
     * 등급 삭제. 그 등급 회원은 바로 아래 등급으로 옮긴다(누적 포인트로 다시 계산한 결과와 같다).
     *
     * @throws ValidationException 기본 등급(0P)을 지우려 할 때
     */
    public function delete(UserLevel $level): void
    {
        if ($level->isBase()) {
            throw ValidationException::withMessages(['min_points' => '기본 등급(0P)은 삭제할 수 없습니다.']);
        }

        DB::transaction(function () use ($level) {
            DB::statement('LOCK TABLE user_levels IN SHARE ROW EXCLUSIVE MODE');

            $lower = UserLevel::query()
                ->where('min_points', '<', $level->min_points)
                ->orderByDesc('min_points')
                ->firstOrFail();

            DB::insert(
                'INSERT INTO user_level_histories (user_id, old_level_id, new_level_id, reason, created_at) SELECT id, user_level_id, ?, ?, ? FROM users WHERE user_level_id = ?',
                [$lower->id, LevelChangeReason::Admin->value, now(), $level->id],
            );
            User::query()->where('user_level_id', $level->id)->update(['user_level_id' => $lower->id]);

            $level->delete();
        });
    }
}
