<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Single source of truth for:
 *   - The 3 user groups (impactCell, followUpOfficer, followUpTeam)
 *   - Which fields each group owns on the Guest model (column-edit policy)
 *   - Helpers to derive a guest's group from their role, and to strip
 *     fields the current role is not allowed to write.
 *   - The 10 seeded role names (Phase 06e+1).
 *
 * Mirrored 1:1 from Implementation/00_Laravel_Bridge.md § 6 so the
 * backend (this file) and the design intent document never drift.
 */
final class RoleHelper
{
    /**
     * 3 user groups — derived from Implementation/03_Three_User_Groups.md.
     */
    public const GROUP_IMPACT_CELL       = ['Impact_Leaders', 'Impact_Cell_Admin', 'Impact_Cell_Report', 'Impact_Zonal_Cordinator'];
    public const GROUP_FOLLOW_UP_OFFICER = ['FollowUpOfficer', 'Follow_UP_Admin'];
    public const GROUP_FOLLOW_UP_TEAM    = ['Follow_UP', 'Follow_UP_View_Only'];

    /**
     * Phase 06e+1 — single source of truth for the 10 seeded role names.
     *
     * Used by:
     *   - Database\Seeders\RolesAndPermissionsSeeder (the original source)
     *   - App\Http\Requests\AdminUserRequest (Form Request validator)
     *   - App\Http\Controllers\Admin\UserController::addableRoles() (used by
     *     the Add User modal's role picker to render the checkbox grid)
     *
     * Adding a new role? Append it here AND to the seeder. Removing one?
     * Drop it here and verify no seeded user still holds it.
     */
    public const ROLE_NAMES = [
        'Administrator',
        'Supervisor',
        'FollowUpOfficer',
        'Follow_UP',
        'Follow_UP_Admin',
        'Follow_UP_View_Only',
        'Impact_Leaders',
        'Impact_Cell_Admin',
        'Impact_Cell_Report',
        'Impact_Zonal_Cordinator',
    ];

    /**
     * Phase 13 — subset of ROLE_NAMES surfaced on the PUBLIC signup
     * form. Administrator / Supervisor / cross-cell-admin / FollowUp
     * roles stay admin-only — provisioning them is `Admin\UserController::store()`
     * territory, not a guest-facing form.
     *
     * As of 2026-07-31 the signup surface shows exactly two roles:
     *   - `Impact_Leaders`        — cell-bound tier, carries
     *                               `users.impact_cell_id` FK + the
     *                               cell-detail seed (leadership team
     *                               roster) on submit.
     *   - `Impact_Zonal_Coordinator` — zone-wide overseer; no cell
     *                               binding at signup (zone is assigned
     *                               by Admin post-signup).
     *
     * FollowUpOfficer / Follow_UP_Admin / Follow_UP / Follow_UP_View_Only
     * were intentionally removed from public signup; Admin still assigns
     * them via /admin/users.
     *
     * Consumed by:
     *   - Auth\RegisteredUserController::create() (Inertia payload)
     *   - Auth\Requests\RegisterInertiaRequest::rules() (role allowlist)
     *
     * Single source of truth: if the public signup matrix changes,
     * change it here ONLY (no separate constants in 2 files to drift).
     */
    public const SIGNUP_VISIBLE_ROLES = [
        'Impact_Leaders',
        'Impact_Zonal_Coordinator',
    ];

    public static function signupVisibleRoles(): array
    {
        return self::SIGNUP_VISIBLE_ROLES;
    }

    /**
     * Phase 09 — single-role constants for cleaner gate expressions in
     * controllers/policies. Use these instead of bare string literals so
     * a role rename only touches RoleHelper::ROLE_NAMES + the named
     * constant (not every `activeRole() === 'Impact_Cell_Admin'` site).
     */
    public const ROLE_ADMIN              = 'Administrator';
    public const ROLE_SUPERVISOR         = 'Supervisor';
    public const ROLE_IMPACT_CELL_ADMIN  = 'Impact_Cell_Admin';
    public const ROLE_IMPACT_ZONAL       = 'Impact_Zonal_Cordinator';

    /** True iff the role is the cross-cell supervisor (Phase 09). */
    public static function isImpactCellAdmin(?string $role): bool
    {
        return $role === self::ROLE_IMPACT_CELL_ADMIN;
    }

    /** 3 group keys — used as array keys in GROUP_GUEST_OWNER */
    public const GROUP_KEY_IMPACT_CELL       = 'impactCell';
    public const GROUP_KEY_FOLLOW_UP_OFFICER = 'followUpOfficer';
    public const GROUP_KEY_FOLLOW_UP_TEAM    = 'followUpTeam';

    /**
     * Column-access matrix — derived from Implementation/03 § Column Policy
     *
     * Keys MUST be snake_case to match what Laravel's HTTP body actually
     * sends (the DB columns are snake_case; the migration is snake_case;
     * GuestResource output is snake_case; the frontend types are snake_case
     * except for the Inertia boundary mapping in DashboardController).
     *
     * History: Phase 01 v1 of this file used camelCase keys by accident
     * and the verifiers were written against the camelCase form. In
     * production that meant every multi-word field was silently stripped
     * from FollowUpOfficer / Team / Impact_Leaders writes. Fixed in the
     * post-Phase-05 follow-up commit (matrix = single source of truth =
     * snake_case to match the wire format).
     */
    public const GROUP_GUEST_OWNER = [
        self::GROUP_KEY_IMPACT_CELL       => ['impact_status', 'nearest_impact_cell_id'],
        self::GROUP_KEY_FOLLOW_UP_OFFICER => [
            'gender', 'marital_status', 'age',
            'phone', 'email', 'address',
            'contacted_status', 'join_when',
            'days_available', 'comments',
            'visited', 'visited_at', 'indicated_to_join',
            'visitation_status', 'feedback',
        ],
        self::GROUP_KEY_FOLLOW_UP_TEAM    => ['follow_up_status', 'follow_up_contacts'],
    ];

    /** Resolve the group key for a role string. null = no group (e.g. Administrator, Supervisor). */
    public static function groupOf(?string $role): ?string
    {
        if ($role === null || $role === '') {
            return null;
        }

        return match (true) {
            in_array($role, self::GROUP_IMPACT_CELL,       true) => self::GROUP_KEY_IMPACT_CELL,
            in_array($role, self::GROUP_FOLLOW_UP_OFFICER, true) => self::GROUP_KEY_FOLLOW_UP_OFFICER,
            in_array($role, self::GROUP_FOLLOW_UP_TEAM,    true) => self::GROUP_KEY_FOLLOW_UP_TEAM,
            default                                            => null,
        };
    }

    /** All field names that ANY group may touch on a Guest record (union of matrices). Useful for display. */
    public static function allGroupOwnedFields(): array
    {
        return Arr::flatten(self::GROUP_GUEST_OWNER);
    }

    /**
     * Permission predicates
     */

    /**
     * True if the role may write the given field on a Guest.
     *
     *  - Administrator: always true.
     *  - Supervisor:     always false (read-only everywhere).
     *  - Per-role special-case (Phase 05): the `follow_officer_id` column is
     *    NOT inside any group's matrix. Per Implementation/03 § "Reassign
     *    within the system", only `Administrator` + `Follow_UP_Admin` may
     *    reassign. Self-assign at create is handled in
     *    `GuestController::store` (forces `follow_officer_id = user.id`
     *    AFTER Form Request stripping) — it's a controller-level
     *    business rule, NOT a matrix entry.
     *  - All other fields: per the GROUP_GUEST_OWNER matrix.
     */
    public static function canEditField(?string $role, string $field): bool
    {
        if (! $role) {
            return false;
        }
        if ($role === 'Administrator') {
            return true;
        }

        if ($field === 'follow_officer_id') {
            return $role === 'Follow_UP_Admin' || $role === 'Impact_Zonal_Cordinator';
        }

        $g = self::groupOf($role);
        if ($g === null) {
            return false; // known role, no group (e.g. Supervisor) → no guest-field writes
        }

        return in_array($field, self::GROUP_GUEST_OWNER[$g], true);
    }

    /**
     * Filter a request body down to fields the role is allowed to write.
     * - Administrator: pass-through.
     * - role in no group: drop everything (defensive default).
     * - role in a group: keep only fields in the group's matrix.
     *
     * Backend is authoritative — the UI uses a mirrored helper.
     */
    public static function stripDisallowed(?string $role, array $body): array
    {
        if ($role === 'Administrator') {
            return $body;
        }
        if (self::groupOf($role) === null) {
            return [];
        }

        return array_filter(
            $body,
            fn ($_, string $key) => self::canEditField($role, $key),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
