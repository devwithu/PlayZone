<?php

namespace App\Enums;

/** 게임 목록 출시 여부 필터 (api-spec §7.2 `release_status`) */
enum GameReleaseFilter: string
{
    /** 한 플랫폼이라도 출시됨 (= 리뷰 가능, 리뷰 정책 §2) */
    case Released = 'released';

    /** 아직 어느 플랫폼에도 출시되지 않았고, 취소되지 않은 출시 계획이 있음 */
    case Upcoming = 'upcoming';
}
