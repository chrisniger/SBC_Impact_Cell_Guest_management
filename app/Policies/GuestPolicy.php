<?php

namespace App\Policies;

use App\Models\Guest;
use App\Models\User;
use App\Support\RoleHelper;

/**
 * Guest policy — Phase 04.
 *
 * Row-level authorization (per bridge § 6 "row-level" + Implementation/03
 * "Assignment rules"). The 3 groups drive the rules:
 *
 *   - Administrator:  full CRUD on all guests.
 *   - Supervisor:      read-only (no create / update / delete).
 *   - FollowUpOfficer / Follow_UP_Admin: can view + update guests where
 *     `follow_officer_id = $user->id`. Can create new guests (self-assigns).
 *   - Impact_Leaders / Impact_Cell_Admin / Impact_Cell_Report: can view
 *     guests where `nearest_impact_cell_id = $user->impact_cell_id`.
 *     Can update the Impact Cell group's columns (impact_status + nearest).
 *   - Follow_UP / Follow_UP_View_Only: read-only on assigned guests
 *     (View_Only) or scoped view (Follow_UP). No create / delete.
 *
 * The Form Request's `prepareForValidation()` enforces the COLUMN-level
 * write policy via `RoleHelper::stripDisallowed()` — this policy enforces
 * the ROW-level access. They are independent layers.
 *
 * Policies are auto-discovered by Laravel 12 (policy at `App\Policies\GuestPolicy`
 * is bound to `App\Models\Guest`).
 */
class GuestPolicy
{
    /**
     * "viewAny" is intentionally permissive — the controller's `index`
     * scopes the query by role (`follow_officer_id` / `nearest_impact_cell_id`)
     * so this is just a gate that returns true for authenticated users.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Viewing a single guest:
     *   - Administrator: always.
     *   - FollowUpOfficer / Follow_UP_Admin: only when assigned to them.
     *   - Impact Cell group: only when the guest's nearest_impact_cell_id
     *     matches the user's impact_cell_id.
     *   - Follow Up / Follow Up View Only: only when assigned to them
     *     (or, for the View_Only subset, never — see destroy/update).
     */
    public function view(User $user, Guest $guest): bool
    {
        $role = $user->activeRole();

        if ($role === 'Administrator') {
            return true;
        }

        return match (RoleHelper::groupOf($role)) {
            'followUpOfficer' => $guest->follow_officer_id === $user->id,
            'impactCell'      => $guest->nearest_impact_cell_id !== null
                                  && $user->impact_cell_id !== null
                                  && $guest->nearest_impact_cell_id === $user->impact_cell_id,
            'followUpTeam'    => $guest->follow_officer_id === $user->id,
            default           => false,   // Supervisor / unknown role
        };
    }

    /**
     * Creating a guest:
     *   - Administrator: always.
     *   - FollowUpOfficer / Follow_UP_Admin: can create (self-assigns).
     *   - Everyone else: NO.
     */
    public function create(User $user): bool
    {
        $role = $user->activeRole();

        if ($role === 'Administrator') {
            return true;
        }

        return in_array($role, ['FollowUpOfficer', 'Follow_UP_Admin'], true);
    }

    /**
     * Updating a guest:
     *   - Administrator: always.
     *   - FollowUpOfficer / Follow_UP_Admin: only when assigned to them.
     *   - Impact Cell group: only when the guest's nearest_impact_cell_id
     *     matches the user's impact_cell_id (and only Impact Cell columns
     *     can be written — but that's the Form Request's job, not the policy's).
     *   - Follow Up / Follow Up View Only: NO.
     */
    public function update(User $user, Guest $guest): bool
    {
        $role = $user->activeRole();

        if ($role === 'Administrator') {
            return true;
        }

        return match (RoleHelper::groupOf($role)) {
            'followUpOfficer' => $guest->follow_officer_id === $user->id,
            'impactCell'      => $guest->nearest_impact_cell_id !== null
                                  && $user->impact_cell_id !== null
                                  && $guest->nearest_impact_cell_id === $user->impact_cell_id,
            default           => false,   // followUpTeam + Supervisor + unknown role
        };
    }

    /**
     * Deleting a guest: Administrator only.
     * (Other roles can soft-delete via the venue workflow, but that's
     *  Phase 11+; for now, deletes are admin-only.)
     */
    public function delete(User $user, Guest $guest): bool
    {
        return $user->activeRole() === 'Administrator';
    }
}
