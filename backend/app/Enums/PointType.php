<?php

namespace App\Enums;

/**
 * 포인트 거래 사유 (포인트 정책 §3, §4). point_transactions.type, point_policies.action 값이다.
 * 앱이 이 값으로 분기하므로 값을 바꾸지 않는다.
 */
enum PointType: string
{
    // 적립 (§3)
    case Signup = 'signup';
    case Attendance = 'attendance';
    case AttendanceStreak7 = 'attendance_streak_7';
    case AttendanceStreak30 = 'attendance_streak_30';
    case ReviewCreate = 'review_create';
    case ReviewLikeReceived = 'review_like_received';
    case PostCreate = 'post_create';
    case CommentCreate = 'comment_create';
    case EventReward = 'event_reward';
    case AdminGrant = 'admin_grant';

    // 사용·차감 (§4)
    case EventEntry = 'event_entry';
    case Revoke = 'revoke';
    case AdminDeduct = 'admin_deduct';
    case WithdrawExpire = 'withdraw_expire';

    public function label(): string
    {
        return match ($this) {
            self::Signup => '회원가입',
            self::Attendance => '출석',
            self::AttendanceStreak7 => '7일 연속 출석',
            self::AttendanceStreak30 => '30일 연속 출석',
            self::ReviewCreate => '리뷰 작성',
            self::ReviewLikeReceived => '리뷰 추천받음',
            self::PostCreate => '게시글 작성',
            self::CommentCreate => '댓글 작성',
            self::EventReward => '이벤트 보상',
            self::AdminGrant => '관리자 지급',
            self::EventEntry => '이벤트 응모',
            self::Revoke => '포인트 회수',
            self::AdminDeduct => '관리자 차감',
            self::WithdrawExpire => '탈퇴로 소멸',
        };
    }

    /**
     * 금액을 point_policies에서 정하는 적립 사유인지. 아니면 부르는 쪽이 금액을 준다(이벤트 보상, 관리자 지급).
     */
    public function usesPolicy(): bool
    {
        return in_array($this, [
            self::Signup, self::Attendance, self::AttendanceStreak7, self::AttendanceStreak30,
            self::ReviewCreate, self::ReviewLikeReceived, self::PostCreate, self::CommentCreate,
        ], true);
    }

    public function isEarning(): bool
    {
        return $this->usesPolicy() || $this === self::EventReward || $this === self::AdminGrant;
    }
}
