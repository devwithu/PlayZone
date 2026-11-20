<?php

namespace App\Enums;

/**
 * API 오류 코드 (api-spec §5). 앱은 message가 아니라 이 값으로 분기하므로 값을 바꾸지 않는다.
 */
enum ApiErrorCode: string
{
    case ValidationFailed = 'VALIDATION_FAILED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case SocialTokenInvalid = 'SOCIAL_TOKEN_INVALID';
    case SignupIncomplete = 'SIGNUP_INCOMPLETE';
    case AccountSuspended = 'ACCOUNT_SUSPENDED';
    case RejoinRestricted = 'REJOIN_RESTRICTED';
    case SocialAccountInUse = 'SOCIAL_ACCOUNT_IN_USE';
    case LastLoginMethod = 'LAST_LOGIN_METHOD';
    case Forbidden = 'FORBIDDEN';
    case ReviewBanned = 'REVIEW_BANNED';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case AttendanceAlreadyDone = 'ATTENDANCE_ALREADY_DONE';
    case ReviewAlreadyExists = 'REVIEW_ALREADY_EXISTS';
    case ReviewHiddenExists = 'REVIEW_HIDDEN_EXISTS';
    case GameNotReleased = 'GAME_NOT_RELEASED';
    case AlreadyReported = 'ALREADY_REPORTED';
    case CannotTargetOwn = 'CANNOT_TARGET_OWN';
    case NotEditable = 'NOT_EDITABLE';
    case InsufficientPoints = 'INSUFFICIENT_POINTS';
    case EventClosed = 'EVENT_CLOSED';
    case BadRequest = 'BAD_REQUEST';
    case RateLimited = 'RATE_LIMITED';
    case ServerError = 'SERVER_ERROR';

    public function status(): int
    {
        return match ($this) {
            self::BadRequest => 400,
            self::Unauthenticated, self::SocialTokenInvalid => 401,
            self::SignupIncomplete, self::AccountSuspended, self::RejoinRestricted,
            self::Forbidden, self::ReviewBanned => 403,
            self::NotFound => 404,
            self::MethodNotAllowed => 405,
            self::SocialAccountInUse, self::LastLoginMethod, self::AttendanceAlreadyDone,
            self::ReviewAlreadyExists, self::ReviewHiddenExists, self::GameNotReleased,
            self::AlreadyReported, self::CannotTargetOwn, self::NotEditable,
            self::InsufficientPoints, self::EventClosed => 409,
            self::ValidationFailed => 422,
            self::RateLimited => 429,
            self::ServerError => 500,
        };
    }

    /**
     * 던지는 쪽에서 메시지를 주지 않았을 때 쓰는 기본 문구 (화면 표시용).
     */
    public function defaultMessage(): string
    {
        return match ($this) {
            self::ValidationFailed => '입력값을 확인해 주세요.',
            self::Unauthenticated => '로그인이 필요합니다.',
            self::SocialTokenInvalid => '소셜 로그인 정보를 확인하지 못했습니다. 다시 로그인해 주세요.',
            self::SignupIncomplete => '가입을 완료해 주세요.',
            self::AccountSuspended => '이용이 정지된 계정입니다.',
            self::RejoinRestricted => '탈퇴 후 30일 동안은 같은 계정으로 다시 가입할 수 없습니다.',
            self::SocialAccountInUse => '이미 다른 회원에 연결된 계정입니다.',
            self::LastLoginMethod => '마지막 로그인 수단은 연결을 해제할 수 없습니다.',
            self::Forbidden => '권한이 없습니다.',
            self::ReviewBanned => '리뷰 작성이 제한된 상태입니다.',
            self::NotFound => '요청한 정보를 찾을 수 없습니다.',
            self::MethodNotAllowed => '지원하지 않는 요청 방식입니다.',
            self::AttendanceAlreadyDone => '이미 오늘 출석을 완료했습니다.',
            self::ReviewAlreadyExists => '이 게임에는 이미 리뷰를 작성했습니다.',
            self::ReviewHiddenExists => '숨김 처리된 리뷰가 있어 새 리뷰를 작성할 수 없습니다.',
            self::GameNotReleased => '출시 전 게임입니다.',
            self::AlreadyReported => '이미 신고했습니다.',
            self::CannotTargetOwn => '본인 글에는 할 수 없습니다.',
            self::NotEditable => '수정할 수 없는 상태입니다.',
            self::InsufficientPoints => '포인트가 부족합니다.',
            self::EventClosed => '참여할 수 없는 이벤트입니다.',
            self::BadRequest => '잘못된 요청입니다.',
            self::RateLimited => '요청이 너무 많습니다. 잠시 후 다시 시도해 주세요.',
            self::ServerError => '일시적인 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.',
        };
    }
}
