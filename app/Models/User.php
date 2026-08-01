<?php

namespace App\Models;

use App\Support\RoleHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string|null $active_role
 * @property \Illuminate\Support\Carbon|null $last_seen_at     * @property string|null $impact_cell_id        Phase 13 — assigned cell (for Impact_Leaders).

 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'active_role',
        // Phase 13 — populated either by Admin on the Edit page or by the
        // registrant when they sign up as Impact_Leaders. Nullable so
        // FollowUpOfficer / Follow_UP_Admin signups that don't pick a cell
        // don't fail the FK existence rule (covered by 'nullable' on the
        // column and 'nullable' rules in RegisterInertiaRequest).
        'impact_cell_id',
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
            'last_seen_at'      => 'datetime',
            'deleted_at'        => 'datetime',
            // Phase 13 — defense-in-depth cast: the column is a UUID string
            // in MySQL, but if a future migration drops the HasUuids trait
            // (e.g. to integer PKs), Eloquent would otherwise emit the raw
            // value without a type. Locking it to 'string' here keeps the
            // JSON wire format stable across refactors.
            'impact_cell_id'    => 'string',
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

    /**
     * Phase 13 — the cell this user leads (set on the public signup
     * form when Impact_Leaders is selected, OR by Admin on
     * /admin/users/{user}/edit). Inverse is `ImpactCell::leaderUsers()`.
     *
     * Uses BelongsTo so Eloquent eager-loads cleanly via
     * `User::with('assignedImpactCell')`. Dashboards that need the
     * roster (LeaderDashboard, ImpactCellAdminDashboard) call this.
     */
    public function assignedImpactCell(): BelongsTo
    {
        return $this->belongsTo(ImpactCell::class, 'impact_cell_id');
    }

    /**
     * Impact Cells covered by an Impact_Zonal_Coordinator.
     * Impact_Leaders continue to use assignedImpactCell() above because
     * their assignment is intentionally single-cell.
     */
    public function zonalImpactCells(): BelongsToMany
    {
        return $this->belongsToMany(ImpactCell::class, 'impact_cell_user');
    }
}
