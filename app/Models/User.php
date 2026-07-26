<?php

namespace App\Models;

use App\Support\RoleHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string|null $active_role
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'active_role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 02 active-role helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The role the multi-role user is currently viewing. Resolves in this order:
     *   1. The persisted `active_role` column (sticky across requests), if it
     *      is still in the user's actual Spatie-roles.
     *   2. The first Spatie role assigned (single-role users land here).
     *   3. null (user has no roles — defensive).
     *
     * USED EVERYWHERE instead of inlining `?? getRoleNames()->first()`.
     * This is the source of truth for the column-policy check in
     * Form Requests and the active_role badge in the app shell.
     */
    public function activeRole(): ?string
    {
        if ($this->active_role !== null && $this->hasRole($this->active_role)) {
            return $this->active_role;
        }

        $first = $this->getRoleNames()->first();

        return $first !== null ? (string) $first : null;
    }

    /** True if the given role is in this user's Spatie roles AND the role is meaningful. */
    public function canSwitchTo(?string $role): bool
    {
        if ($role === null || $role === '') {
            return false;
        }
        if (! $this->hasRole($role)) {
            return false;
        }

        // Administrator can switch to anything for QA, even roles they technically don't hold
        // (e.g. temporarily impersonating a group). Keep it explicit:
        return true;
    }

    /** Convenience: group key for the active role (impactCell / followUpOfficer / followUpTeam / null). */
    public function activeGroup(): ?string
    {
        return RoleHelper::groupOf($this->activeRole());
    }
}
