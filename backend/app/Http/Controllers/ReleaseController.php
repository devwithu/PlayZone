<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\V1\ReleaseScheduleRequest;
use App\Http\Resources\Api\V1\ReleaseScheduleResource;
use App\Http\Resources\Api\V1\TaxonomyResource;
use App\Models\Platform;
use App\Services\ReleaseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 웹 출시 일정 (화면 설계 W-08). 월 단위로 보고 플랫폼 하나로 거른다.
 * 주소창에서 잘못 바뀐 값(month, platform)은 오류 대신 이번 달·전체 플랫폼으로 보여 준다.
 */
class ReleaseController extends Controller
{
    public function __construct(private readonly ReleaseService $releases) {}

    public function index(Request $request): Response
    {
        $month = $this->month($request->string('month')->value());
        $platform = $request->string('platform')->value();
        $platform = preg_match(ReleaseScheduleRequest::SLUG_LIST, $platform) === 1 && ! str_contains($platform, ',') ? $platform : '';

        $entries = $this->releases->schedule(
            $month,
            $month->endOfMonth()->startOfDay(),
            $platform === '' ? [] : [$platform],
        );

        return Inertia::render('releases/Index', [
            'month' => $month->format('Y-m'),
            'prevMonth' => $month->subMonth()->format('Y-m'),
            'nextMonth' => $month->addMonth()->format('Y-m'),
            'today' => ReleaseService::today()->toDateString(),
            'platform' => $platform,
            'entries' => ReleaseScheduleResource::collection($entries)->toResponse($request)->getData(true)['data'],
            'platforms' => TaxonomyResource::collection(Platform::query()->ordered()->get())->resolve($request),
        ]);
    }

    /**
     * `YYYY-MM`이면 그달 1일, 아니면 이번 달 1일 (KST)
     */
    private function month(string $value): CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m) === 1 && (int) $m[1] >= 1970 && (int) $m[1] <= 2100) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1, 0, 0, 0, ReleaseService::TIMEZONE);
        }

        return ReleaseService::today()->startOfMonth();
    }
}
