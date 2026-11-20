<?php

namespace App\Enums;

/** 게임 목록 정렬 (api-spec §7.2 `sort`) */
enum GameSort: string
{
    /** 리뷰 많은 순 (기본) */
    case Popular = 'popular';

    /** 평균 점수 높은 순. 평균이 없는 게임(리뷰 3개 미만)은 뒤로 */
    case Rating = 'rating';

    /** 최근 출시 순 (출시된 플랫폼 중 가장 최근 출시일) */
    case LatestRelease = 'latest_release';

    /** 제목 가나다순 */
    case Title = 'title';
}
