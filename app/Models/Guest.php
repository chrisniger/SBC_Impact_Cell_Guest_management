<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Guest model — Phase 04.
 *
 * Column-level access is enforced at the Form Request layer (via
 * App\Http\Requests\GuestRequest + App\Support\RoleHelper::stripDisallowed)
 * and at the JWT-tasting policy layer (App\Policies\GuestPolicy). The model
 * itself has no per-field authorization — that's the controller's job.
 *
 * @property string      $id
 * @property \Carbon\CarbonInterface|null $date
 * @property string|null $event
 * @property string|null $event_other
 * @property string      $guest_name             // never null — required
 * @property string|null $source
 * @property string|null $gender
 * @property string|null $marital_status
 * @property string|null $age                    // String per v2 schema
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $nearest_impact_cell_id
 * @property string|null $impact_status
 * @property string|null $contacted_status
 * @property string|null $join_when
 * @property string|null $days_available
 * @property string|null $comments
 * @property bool        $visited
 * @property string|null $visited_at
 * @property string|null $indicated_to_join
 * @property string|null $visitation_status
 * @property string|null $feedback
 * @property string|null $follow_up_status
 * @property array<mixed>|null $follow_up_contacts
 * @property string|null $follow_officer_id
 */
class Guest extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * The only `impact_status` values the system will persist.
     *
     * Single source of truth for BOTH write paths:
     *   - `GuestController::updateImpactStatus()` (inline pill)
     *   - `GuestRequest` (full-edit form)
     * Mirrors `InlineImpactStatusPill.tsx` STATUSES. NULL = not yet marked
     * (pending). Kept as a plain string list (not a backed PHP enum) so the
     * DB column stays a nullable varchar and legacy/CSV-imported rows are
     * still readable (they're bucketed defensively at read time). NOTE: a
     * legacy value outside this list will 422 on a full-edit save until it's
     * normalized (the Edit form surfaces it as a disabled "legacy" option) —
     * CSV import never writes impact_status, so only pre-existing DB rows
     * can hold such values.
     */
    public const IMPACT_STATUSES = ['Contacted', 'Not Contacted', 'Not Reachable'];

    protected $fillable = [
        'date',
        'event',
        'event_other',
        'guest_name',
        'source',
        'gender',
        'marital_status',
        'age',
        'phone',
        'email',
        'address',
        'nearest_impact_cell_id',
        'impact_status',
        'contacted_status',
        'join_when',
        'days_available',
        'comments',
        'visited',
        'visited_at',
        'indicated_to_join',
        'visitation_status',
        'feedback',
        'follow_up_status',
        'follow_up_contacts',
        'follow_officer_id',
    ];

    protected $casts = [
        'date'               => 'datetime',
        'visited'            => 'boolean',
        'follow_up_contacts' => 'array',
        'deleted_at'         => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType   = 'string';

    // ─────────────────────────────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────────────────────────────

    /**
     * The Impact Cell closest to this guest's address (best-effort manual
     * assignment by an Impact Cell group member).
     */
    public function nearestImpactCell(): BelongsTo
    {
        return $this->belongsTo(ImpactCell::class, 'nearest_impact_cell_id');
    }

    /**
     * The Follow Up Officer (group member) this guest is assigned to.
     * Used by GuestPolicy::view/update to scope non-admin reads.
     */
    public function followOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follow_officer_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes — used by GuestController@index + the per-group dashboards
    // ─────────────────────────────────────────────────────────────────────

    /** Scope to a specific follow-up officer (their assigned list). */
    public function scopeAssignedTo($query, string $userId)
    {
        return $query->where('follow_officer_id', $userId);
    }

    /** Scope to a specific Impact Cell (the cell leader's guest list). */
    public function scopeForImpactCell($query, string $impactCellId)
    {
        return $query->where('nearest_impact_cell_id', $impactCellId);
    }
}
