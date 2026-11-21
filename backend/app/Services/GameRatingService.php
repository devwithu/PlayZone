<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Models\Game;
use App\Models\GameRatingStat;
use App\Models\GameReview;
use App\Models\RatingCategory;
use Illuminate\Support\Facades\DB;

/**
 * 게임 평균 점수 집계 (리뷰 정책 §9, ERD §5).
 *
 * - games.review_count: 공개 리뷰 수. games.rating_avg: 공개 리뷰 종합 점수 평균(소수 첫째 자리), 3개 미만이면 NULL.
 * - game_rating_stats: 세부 항목별로 점수를 준 공개 리뷰만의 평균.
 * - 리뷰 작성·수정·상태 변경이 커밋된 뒤 다시 계산한다(afterCommit). 원본에서 매번 새로 계산하므로 몇 번 불러도 같다.
 * - 게임 행을 잠가서 같은 게임의 재계산이 겹쳐 오래된 값으로 덮어쓰지 않게 한다.
 */
class GameRatingService
{
    /** 평균을 보여 주는 최소 공개 리뷰 수 */
    public const MIN_REVIEWS_FOR_AVERAGE = 3;

    /**
     * 지금 트랜잭션이 커밋되면 다시 계산한다. 트랜잭션 밖이면 바로 계산한다.
     */
    public function recalculateAfterCommit(int $gameId): void
    {
        DB::afterCommit(fn () => $this->recalculate($gameId));
    }

    public function recalculate(int $gameId): void
    {
        DB::transaction(function () use ($gameId) {
            $game = Game::query()->lockForUpdate()->find($gameId);
            if ($game === null) {
                return;
            }

            $published = GameReview::query()->where('game_id', $gameId)->where('status', ReviewStatus::Published);
            $count = (clone $published)->count();
            $avg = $count >= self::MIN_REVIEWS_FOR_AVERAGE ? round((float) (clone $published)->avg('overall_score'), 1) : null;

            $game->forceFill(['review_count' => $count, 'rating_avg' => $avg])->saveQuietly();

            $stats = DB::table('review_ratings')
                ->join('game_reviews', 'game_reviews.id', '=', 'review_ratings.review_id')
                ->where('game_reviews.game_id', $gameId)
                ->where('game_reviews.status', ReviewStatus::Published->value)
                ->groupBy('review_ratings.rating_category_id')
                ->selectRaw('review_ratings.rating_category_id, ROUND(AVG(review_ratings.score), 1) AS avg_score, COUNT(*) AS score_count')
                ->get();

            GameRatingStat::query()->where('game_id', $gameId)->delete();
            foreach ($stats as $stat) {
                GameRatingStat::query()->insert([
                    'game_id' => $gameId,
                    'rating_category_id' => $stat->rating_category_id,
                    'avg_score' => $stat->avg_score,
                    'score_count' => $stat->score_count,
                ]);
            }
        });
    }

    /**
     * 점수 분포와 세부 항목 평균 (GET /games/{id}/rating-summary, 게임 상세 화면).
     * 공개 리뷰가 3개 미만이면 평균들은 null이고 분포와 리뷰 수만 준다.
     *
     * @return array{review_count: int, rating_avg: float|null, distribution: array<int, int>, categories: list<array{slug: string, name: string, avg_score: float|null, score_count: int}>}
     */
    public function summary(Game $game): array
    {
        $counts = GameReview::query()
            ->where('game_id', $game->id)
            ->where('status', ReviewStatus::Published)
            ->groupBy('overall_score')
            ->selectRaw('overall_score, COUNT(*) AS review_count')
            ->pluck('review_count', 'overall_score');

        $distribution = [];
        for ($score = 1; $score <= 10; $score++) {
            $distribution[$score] = (int) ($counts[$score] ?? 0);
        }

        $enough = $game->review_count >= self::MIN_REVIEWS_FOR_AVERAGE;
        $stats = GameRatingStat::query()->where('game_id', $game->id)->get()->keyBy('rating_category_id');

        $categories = [];
        foreach (RatingCategory::query()->active()->ordered()->get() as $category) {
            /** @var GameRatingStat|null $stat */
            $stat = $stats->get($category->id);
            $categories[] = [
                'slug' => $category->slug,
                'name' => $category->name,
                'avg_score' => $enough && $stat !== null ? $stat->avg_score : null,
                'score_count' => $stat->score_count ?? 0,
            ];
        }

        return [
            'review_count' => $game->review_count,
            'rating_avg' => $game->rating_avg,
            'distribution' => $distribution,
            'categories' => $categories,
        ];
    }
}
