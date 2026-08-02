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
 *   - Impact_Leaders: leadership-team edit on their OWN cell only
 *     (via updateLeadership; never name/hierarchy — see below).
 *   - Everyone else (incl. Impact_Cell_Admin): read-only
 *     (`view` + `viewAny` only). Phase 35 — Impact_Cell_Admin was
 *     previously allowed create/update/updateLeadership on any cell;
 *     that write tier was removed so the role is a pure viewer of the
 *     Impact Cells surface (add/edit lives behind Administrator).
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

    /**
     * Phase 09 + Phase 35 — Create: Administrator ONLY.
     *
     * Phase 35 — Impact_Cell_Admin was read-only'd on the Impact Cells
     * surface (view, no add/edit); creating cells is now Administrator-
     * only. The Add-button gate on ImpactCells/Index mirrors this.
     */
    public function create(User $user): bool
    {
        return $user->activeRole() === 'Administrator';
    }

    /** Phase 09 + Phase 35 — Update: same gate as create() (Administrator only). */
    public function update(User $user, ImpactCell $cell): bool
    {
        return $user->activeRole() === 'Administrator';
    }

    /**
     * Phase 32 — Leadership-team edit: narrow, non-hierarchy write.
     *
     * Gates the dedicated PUT /impact-cells/{id}/leadership endpoint which
     * accepts ONLY the 6 free-text leadership columns (leader/assistant/
     * welfare officer name + phone). Two tiers:
     *
     *   - Administrator: any cell (same as update()).
     *   - Impact_Leaders: ONLY the cell they are assigned to
     *     (`users.impact_cell_id === cell.id`). This is the "let the
     *     leader edit their own leadership team but NOT the cell name"
     *     contract: the leadership endpoint never accepts `name`, so a
     *     leader physically cannot rename their cell through it, while
     *     the full `update()` (which owns name/hierarchy) stays
     *     Administrator-only via ImpactCellPolicy::update.
     *
     * Phase 35 — Impact_Cell_Admin (previously admin-tier on any cell)
     * was dropped from this gate so the role is read-only on the
     * Impact Cells surface.
     */
    public function updateLeadership(User $user, ImpactCell $cell): bool
    {
        $role = $user->activeRole();

        if ($role === 'Administrator') {
            return true;
        }

        return $role === 'Impact_Leaders'
            && $user->impact_cell_id !== null
            && $user->impact_cell_id === $cell->id;
    }

    /**
     * Phase 09 — Delete: Administrator ONLY.
     * Cell deletion teardown touches global system state (sub-cell cascades,
     * leadership tree recompute, submission history) — a blast radius we keep
     * behind the top-level admin role.
     */
    public function delete(User $user, ImpactCell $cell): bool
    {
        return $user->activeRole() === 'Administrator';
    }
}
