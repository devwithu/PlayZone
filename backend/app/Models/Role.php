<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug member / moderator / admin / super_admin
 */
#[Fillable(['name', 'slug'])]
class Role extends Model
{
    public const MEMBER = 'member';

    public const MODERATOR = 'moderator';

    public const ADMIN = 'admin';

    public const SUPER_ADMIN = 'super_admin';

    /** 관리자 화면(/admin)에 들어올 수 있는 역할 (ADR-003 §6) */
    public const PANEL_ROLES = [self::MODERATOR, self::ADMIN, self::SUPER_ADMIN];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
