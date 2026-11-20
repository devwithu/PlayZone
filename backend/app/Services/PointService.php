<?php

namespace App\Services;

use App\Data\PointResult;
use App\Enums\ApiErrorCode;
use App\Enums\PointType;
use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\PointPolicy;
use App\Models\PointTransaction;
use App\Models\User;
use App\Support\ServiceDate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 포인트 적립·차감 (포인트 정책 §2, §6). 모든 포인트 변경은 이 서비스를 거친다.
 *
 * - 원장(point_transactions)이 원본이다. users.point_balance는 항상 원장 합계와 같다.
 * - 한 지급 사유에는 한 번만 지급한다: 원장의 idempotency_key UNIQUE. 같은 키로 다시 부르면 "이미 처리됨"으로 끝낸다.
 * - 같은 회원의 처리는 회원 행 잠금(SELECT … FOR UPDATE)으로 한 번에 하나씩 한다.
 * - 금액과 날짜는 서버가 정한다. 하루 기준은 KST(ServiceDate).
 *
 * 각 메서드는 자체 트랜잭션을 연다. 가입 완료·탈퇴처럼 바깥 트랜잭션 안에서 부르면 그 트랜잭션에 포함된다.
 * 등급 반영과 알림은 등급 정책이 정해지면 커밋 뒤 처리로 추가한다 (§6-7, §9).
 */
class PointService
{
    /**
     * 적립. 정책 사유(PointType::usesPolicy)는 point_policies의 금액·일일 한도를 따르고,
     * 이벤트 보상·관리자 지급은 $amount를 받는다. 활성 회원에게만 지급한다.
     *
     * @param  string  $key  중복 방지 키 (포인트 정책 §5 형식, 예: `signup:{user_id}`)
     */
    public function earn(
        User $user,
        PointType $type,
        string $key,
        ?int $amount = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?User $admin = null,
    ): PointResult {
        if (! $type->isEarning()) {
            throw new InvalidArgumentException("{$type->value}는 적립 사유가 아닙니다.");
        }
        if (! $type->usesPolicy() && ($amount === null || $amount <= 0)) {
            throw new InvalidArgumentException("{$type->value}는 1 이상의 금액이 필요합니다.");
        }

        return DB::transaction(function () use ($user, $type, $key, $amount, $referenceType, $referenceId, $description, $admin) {
            $user = $this->lock($user);

            if (($existing = $this->findByKey($key)) !== null) {
                return new PointResult($type, 0, $user->point_balance, alreadyProcessed: true, transaction: $existing);
            }

            if ($user->status !== UserStatus::Active) {
                return new PointResult($type, 0, $user->point_balance);
            }

            $limited = false;
            if ($type->usesPolicy()) {
                $policy = PointPolicy::query()->where('action', $type->value)->first();
                if ($policy === null || ! $policy->is_active) {
                    return new PointResult($type, 0, $user->point_balance);
                }

                [$amount, $limited] = $this->applyDailyLimit($user, $type, $policy);
                if ($amount === 0) {
                    return new PointResult($type, 0, $user->point_balance, limited: true);
                }
            }

            /** @var int $amount */
            $transaction = $this->record($user, $type, $amount, $key, $referenceType, $referenceId, $description, $admin);
            $user->lifetime_points += $amount;
            $user->save();

            return new PointResult($type, $amount, $user->point_balance, $limited, transaction: $transaction);
        });
    }

    /**
     * 사용·차감 (이벤트 응모, 관리자 차감). 잔액이 모자라면 `409 INSUFFICIENT_POINTS`.
     *
     * @param  int  $amount  차감할 포인트 (양수)
     */
    public function spend(
        User $user,
        PointType $type,
        int $amount,
        string $key,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?User $admin = null,
    ): PointResult {
        if (! in_array($type, [PointType::EventEntry, PointType::AdminDeduct], true)) {
            throw new InvalidArgumentException("{$type->value}는 spend()로 처리하지 않습니다.");
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('차감 포인트는 1 이상이어야 합니다.');
        }

        return DB::transaction(function () use ($user, $type, $amount, $key, $referenceType, $referenceId, $description, $admin) {
            $user = $this->lock($user);

            if (($existing = $this->findByKey($key)) !== null) {
                return new PointResult($type, 0, $user->point_balance, alreadyProcessed: true, transaction: $existing);
            }

            if ($user->point_balance < $amount) {
                throw ApiException::of(ApiErrorCode::InsufficientPoints);
            }

            $transaction = $this->record($user, $type, -$amount, $key, $referenceType, $referenceId, $description, $admin);
            $user->save();

            return new PointResult($type, -$amount, $user->point_balance, transaction: $transaction);
        });
    }

    /**
     * 부정 지급 회수 (포인트 정책 §7). 잔액만큼만 회수하고 모자란 금액은 설명에 남긴다.
     * 누적 획득 포인트에서도 회수한 만큼 뺀다(§9). 잔액이 0이면 회수할 것이 없어 아무것도 남기지 않는다.
     */
    public function revoke(PointTransaction $original, ?string $reason = null, ?User $admin = null): PointResult
    {
        if ($original->amount <= 0 || ! $original->type->isEarning()) {
            throw new InvalidArgumentException('적립 거래만 회수할 수 있습니다.');
        }

        return DB::transaction(function () use ($original, $reason, $admin) {
            $user = $this->lock($original->user);
            $key = "revoke:{$original->id}";

            if (($existing = $this->findByKey($key)) !== null) {
                return new PointResult(PointType::Revoke, 0, $user->point_balance, alreadyProcessed: true, transaction: $existing);
            }

            $amount = min($original->amount, $user->point_balance);
            if ($amount === 0) {
                return new PointResult(PointType::Revoke, 0, $user->point_balance);
            }

            $shortfall = $original->amount - $amount;
            $description = trim(($reason ?? '').($shortfall > 0 ? " (잔액 부족으로 {$shortfall}P 미회수)" : '')) ?: null;

            $transaction = $this->record($user, PointType::Revoke, -$amount, $key, 'point_transaction', $original->id, $description, $admin);
            $user->lifetime_points = max(0, $user->lifetime_points - $amount);
            $user->save();

            return new PointResult(PointType::Revoke, -$amount, $user->point_balance, transaction: $transaction);
        });
    }

    /**
     * 탈퇴 시 잔액 소멸 (포인트 정책 §10-6). 기존 원장은 남기고 소멸 거래를 추가한다. 잔액이 없으면 null.
     */
    public function expireOnWithdraw(User $user): ?PointResult
    {
        return DB::transaction(function () use ($user) {
            $user = $this->lock($user);
            $key = "withdraw_expire:{$user->id}";

            if ($user->point_balance === 0 || $this->findByKey($key) !== null) {
                return null;
            }

            $amount = $user->point_balance;
            $transaction = $this->record($user, PointType::WithdrawExpire, -$amount, $key);
            $user->save();

            return new PointResult(PointType::WithdrawExpire, -$amount, 0, transaction: $transaction);
        });
    }

    /**
     * 오늘(KST) 이미 받은 횟수·포인트로 지급액을 정한다. 포인트 한도는 남은 만큼만 지급한다.
     *
     * @return array{0: int, 1: bool} [지급액, 한도에 걸렸는지]
     */
    private function applyDailyLimit(User $user, PointType $type, PointPolicy $policy): array
    {
        $today = PointTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->where('service_date', ServiceDate::today()->toDateString());

        if ($policy->daily_limit_count !== null && (clone $today)->count() >= $policy->daily_limit_count) {
            return [0, true];
        }

        if ($policy->daily_limit_points !== null) {
            $remaining = $policy->daily_limit_points - (int) (clone $today)->sum('amount');
            if ($remaining <= 0) {
                return [0, true];
            }
            if ($policy->points > $remaining) {
                return [$remaining, true];
            }
        }

        return [$policy->points, false];
    }

    /**
     * 원장에 거래를 추가하고 회원 잔액을 맞춘다(저장은 부르는 쪽). $user는 잠긴 행이어야 한다.
     */
    private function record(
        User $user,
        PointType $type,
        int $amount,
        string $key,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?User $admin = null,
    ): PointTransaction {
        $user->point_balance += $amount;

        return PointTransaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'balance_after' => $user->point_balance,
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'idempotency_key' => $key,
            'description' => $description === null ? null : mb_substr($description, 0, 500),
            'admin_id' => $admin?->id,
            'service_date' => ServiceDate::today()->toDateString(),
            'created_at' => now(),
        ]);
    }

    private function lock(User $user): User
    {
        return User::query()->lockForUpdate()->findOrFail($user->id);
    }

    private function findByKey(string $key): ?PointTransaction
    {
        return PointTransaction::query()->where('idempotency_key', $key)->first();
    }
}
