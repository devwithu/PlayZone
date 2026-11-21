<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 회원 프로필 (ERD §3). ProfileService만 쓴다.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $profile_image_path
 * @property string|null $introduction
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 */
class UserProfile extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function imageUrl(): ?string
    {
        return $this->profile_image_path === null ? null : Storage::url($this->profile_image_path);
    }
}
