<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string      $id
 * @property string      $name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $parent_cell_id
 * @property bool        $is_primary
 * @property int         $order
 * @property string|null $leader_name               Free-text per Phase 13 leader-board UX decision.
 * @property string|null $leader_phone
 * @property string|null $assistant_name
 * @property string|null $assistant_phone
 * @property string|null $welfare_officer_name
 * @property string|null $welfare_officer_phone
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int,User> $leaderUsers
 *                                                        Users whose impact_cell_id points at this cell
 *                                                        (computed via the inverse of User::assignedImpactCell()).
 */
class ImpactCell extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'parent_cell_id',
        'is_primary',
        'order',
        // Phase 13 — free-text leadership team seeded at signup or by
        // admin via the inline edit on the Show page.
        'leader_name',
        'leader_phone',
        'assistant_name',
        'assistant_phone',
        'welfare_officer_name',
        'welfare_officer_phone',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'order'      => 'integer',
    ];

    public $incrementing = false;
    protected $keyType   = 'string';

    // ─────────────────────────────────────────────────────────────────────
    // Self-relations
    // ─────────────────────────────────────────────────────────────────────

    /** Parent cell (the primary cell this sub-cell belongs to). null for primaries. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_cell_id');
    }

    /** Sub-cells that hang off this primary. Empty for non-primaries / leaves. */
    public function subCells(): HasMany
    {
        return $this->hasMany(self::class, 'parent_cell_id');
    }

    /**
     * Phase 13 — inverse of `User::assignedImpactCell()`.
     * Returns the users (most often a single Impact_Leaders) whose
     * `impact_cell_id` points at this cell. Used by Show page
     * "Assigned leader" badge + future leadership-board roster.
     */
    public function leaderUsers(): HasMany
    {
        return $this->hasMany(User::class, 'impact_cell_id');
    }

    /** Users assigned to this cell as zonal coordinators. */
    public function zonalUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'impact_cell_user');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Hierarchy validators — mirror Implementation/04 server/lib/impact-cells.js
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Returns the array of errors (use for bulk validation in Form Requests,
     * or call hierarchyRulesOrThrow() to use the throw-based contract).
     *
     * `?ImpactCell $existing` is OPTIONAL — pass it for UPDATE / DEMOTE flows
     * so the model can also enforce "a primary cell that already has active
     * sub-cells cannot be demoted to a sub-cell itself" (the Phase 17
     * "grandparent trap" guard). For CREATE flows (no existing row yet),
     * leave it null and the demo guard is silently skipped.
     */
    public static function hierarchyRules(bool $isPrimary, ?string $parentCellId, ?self $existing = null): array
    {
        // Normalize empty string to null — form submissions often send '' for unset fields.
        $parentCellId = ($parentCellId === '' || $parentCellId === null) ? null : $parentCellId;

        $errors = [];
        if ($isPrimary && $parentCellId !== null) {
            $errors[] = 'A primary cell cannot have a parent.';
        }
        if (! $isPrimary && $parentCellId === null) {
            $errors[] = 'A non-primary cell must have a parent.';
        }
        // Phase 17 — grandparent-trap guard. If we're demoting an existing
        // primary with its own sub-cells to a sub-cell itself, we'd silently
        // create grandchildren under a sub-cell (3-level hierarchy), which
        // Phase 13b explicitly forbade ("1-level hierarchy"). Demoting is fine
        // ONLY if the primary currently has no children — admin must detach
        // or delete them first.
        if ($existing !== null && ! $isPrimary && $existing->is_primary && $existing->subCells()->exists()) {
            $errors[] = 'A primary cell with active sub-cells cannot be demoted to a sub-cell. Detach or delete its children first.';
        }
        return $errors;
    }

    /** Throw-based variant — used by controllers so all hierarchy errors flow through one catch. */
    public static function hierarchyRulesOrThrow(bool $isPrimary, ?string $parentCellId, ?self $existing = null): void
    {
        $errors = self::hierarchyRules($isPrimary, $parentCellId, $existing);
        if ($errors !== []) {
            throw new \DomainException(implode(' ', $errors));
        }
    }

    /**
     * Throws if the proposed parent doesn't exist OR isn't a primary cell.
     * 1-level hierarchy per Implementation/04 § "Final decision": no grandchildren.
     */
    public static function ensureParentIsPrimary(?string $parentCellId): void
    {
        if ($parentCellId === null) {
            return;
        }
        $parent = self::find($parentCellId);
        if ($parent === null) {
            throw new \DomainException("Parent cell {$parentCellId} not found.");
        }
        if (! $parent->is_primary) {
            throw new \DomainException(
                'Only primary cells can have sub-cells (1-level hierarchy).'
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeSub($query)
    {
        return $query->where('is_primary', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }
}