<?php

namespace App\Http\Controllers;

use App\Enums\RankingType;
use App\Models\User;
use App\Services\RankingService;
use App\Support\ServiceDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 웹 랭킹 (화면 설계 W-13, REQ-508). 공개 화면이라 SSR로 그린다.
 * 주소창에서 잘못 바뀐 type·date는 오류 대신 기본값(주간 포인트, 이번 기간)으로 보여 준다.
 */
class RankingController extends Controller
{
    public function __construct(private readonly RankingService $rankings) {}

    public function index(Request $request): Response
    {
        $type = RankingType::tryFrom($request->string('type')->value()) ?? RankingType::PointsWeekly;
        $date = $request->string('date')->value();
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))
            ? CarbonImmutable::parse($date, ServiceDate::TIMEZONE)
            : null;

        /** @var User|null $viewer */
        $viewer = $request->user();

        return Inertia::render('rankings/Index', [
            'ranking' => $this->rankings->board($type, $date, $viewer),
            'types' => array_map(fn (RankingType $t) => ['value' => $t->value, 'label' => $t->label(), 'unit' => $t->scoreUnit()], RankingType::cases()),
        ]);
    }
}
