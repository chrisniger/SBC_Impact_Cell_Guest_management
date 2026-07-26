<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string      $id
 * @property string      $name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $parent_cell_id
 * @property bool        $is_primary
 * @property int         $order
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

    // ─────────────────────────────────────────────────────────────────────
    // Hierarchy validators — mirror Implementation/04 server/lib/impact-cells.js
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Returns the array of errors (use for bulk validation in Form Requests,
     * or call hierarchyRulesOrThrow() to use the throw-based contract).
     */
    public static function hierarchyRules(bool $isPrimary, ?string $parentCellId): array
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
        return $errors;
    }

    /** Throw-based variant — used by controllers so all hierarchy errors flow through one catch. */
    public static function hierarchyRulesOrThrow(bool $isPrimary, ?string $parentCellId): void
    {
        $errors = self::hierarchyRules($isPrimary, $parentCellId);
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('name');
    }
}