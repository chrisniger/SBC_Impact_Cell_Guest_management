<?php

namespace App\Policies;

use App\Models\ImpactCell;
use App\Models\User;

/**
 * Impact Cell policy — Phase 03 follow-up (per HANDOFF.md § 8 #7 +
 * the inline TODO in `ImpactCellController`).
 *
 * Column-level access for the cells themselves:
 *   - Administrator: full CRUD on all cells.
 *   - Everyone else: read-only (`view` + `viewAny` only).
 *
 * Note: `impact_cell_id` (the user's assigned cell) is unaffected by this
 * policy — that's a User column write, not an ImpactCell write. This policy
 * only covers writes to the `impact_cells` table.
 *
 * Auto-discovered by Laravel 12 (policy at `App\Policies\ImpactCellPolicy`
 * is bound to `App\Models\ImpactCell`).
 */
class ImpactCellPolicy
{
    /** Read-only listing — anyone authenticated can list cells. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Read — anyone authenticated. */
    public function view(User $user, ImpactCell $cell): bool
    {
        return true;
    }

    /** Create — Administrator only. */
    public function create(User $user): bool
    {
        return $user->activeRole() === 'Administrator';
    }

    /** Update — Administrator only. */
    public function update(User $user, ImpactCell $cell): bool
    {
        return $user->activeRole() === 'Administrator';
    }

    /** Delete — Administrator only. */
    public function delete(User $user, ImpactCell $cell): bool
    {
        return $user->activeRole() === 'Administrator';
    }
}
