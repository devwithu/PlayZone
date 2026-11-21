<?php

namespace App\Console\Commands;

use App\Enums\RankingType;
use App\Services\RankingService;
use App\Support\ServiceDate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * 랭킹 집계 (랭킹 정책 §3). 스케줄러가 매시 정각에 부른다(routes/console.php).
 * 옵션 없이 부르면 이번 기간(+ 새 기간 첫날이면 지난 기간)을, --type·--date를 주면 그 기간만 다시 집계한다.
 */
#[Signature('rankings:snapshot {--type= : points_weekly 또는 reviews_monthly} {--date= : 기간 안의 아무 날짜 (YYYY-MM-DD, KST)}')]
#[Description('랭킹을 집계해 ranking_snapshots에 저장한다')]
class SnapshotRankingsCommand extends Command
{
    public function handle(RankingService $rankings): int
    {
        $typeOption = $this->option('type');
        $dateOption = $this->option('date');

        if ($typeOption === null && $dateOption === null) {
            foreach ($rankings->snapshotCurrent() as $period => $count) {
                $this->line("{$period}: {$count}명");
            }

            return self::SUCCESS;
        }

        $type = RankingType::tryFrom((string) $typeOption);
        $date = is_string($dateOption) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOption) === 1
            ? CarbonImmutable::parse($dateOption, ServiceDate::TIMEZONE)
            : ServiceDate::today();
        if ($type === null) {
            $this->error('--type은 points_weekly 또는 reviews_monthly여야 합니다.');

            return self::FAILURE;
        }

        $count = $rankings->snapshot($type, $date);
        $this->line("{$type->value} {$type->periodStart($date)->toDateString()}: {$count}명");

        return self::SUCCESS;
    }
}
