<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 14 — rename the typo'd `Impact_Zonal_Coordinator` row in the
 * `roles` table to the correct spelling `Impact_Zonal_Coordinator`.
 *
 * Background
 * ----------
 * On 2026-07-31 the public signup path (`POST /register`) crashed on
 * production with:
 *
 *     Spatie\Permission\Exceptions\RoleDoesNotExist
 *     There is no role named `Impact_Zonal_Coordinator` for guard `web`.
 *
 * Root cause was a one-character spelling drift between two constants
 * inside the same file (`app/Support/RoleHelper.php`):
 *
 *   - `RoleHelper::ROLE_NAMES[9]`            = 'Impact_Zonal_Coordinator' (TYPO, missing one 'o')
 *   - `RoleHelper::SIGNUP_VISIBLE_ROLES[1]`  = 'Impact_Zonal_Coordinator' (CORRECT)
 *   - `RoleHelper::GROUP_IMPACT_CELL[3]`     = 'Impact_Zonal_Coordinator' (TYPO)
 *   - `RoleHelper::ROLE_IMPACT_ZONAL`        = 'Impact_Zonal_Coordinator' (TYPO)
 *
 * The seeder (`RolesAndPermissionsSeeder`) reads `ROLE_NAMES`, so the
 * `roles` table gained a row under the TYPO'd spelling. The signup
 * FormRequest validates against `SIGNUP_VISIBLE_ROLES` (CORRECT).
 * `User::syncRoles(['Impact_Zonal_Coordinator'])` then looked for the
 * correct spelling in the `roles` table — found nothing — and threw
 * `RoleDoesNotExist`.
 *
 * The Phase-14 fix is two-pronged:
 *
 *   1) Correct the constants in `RoleHelper.php` (commit on this branch).
 *   2) Bring EXISTING production rows in line via this migration.
 *
 * Idempotency
 * -----------
 * - `up()` is idempotent: after the first run, `WHERE name = 'Impact_Zonal_Coordinator'`
 *   selects 0 rows, so re-running is a no-op.
 * - `down()` is provided for symmetry. The typo was a code bug, not an
 *   operational invariant, so users should not need to roll back in
 *   practice — but a reverse UPDATE keeps the migration honest.
 *
 * No Spatie-permission-table touchups required: `role_has_permissions`
 * keys on `role_id` (FK), not `name`, so a `roles.name` rename auto-
 * follows the FK without orphaning any permission grants.
 *
 * Compose with
 * ------------
 * - `php artisan roles:audit` — Phase-14 smoke command. Verifies
 *   `SIGNUP_VISIBLE_ROLES ⊆ ROLE_NAMES` AND that all expected role
 *   rows are present on `guard=web`. If a future PR drifts the two
 *   constants again, the audit fails on deploy and the typo cannot
 *   make it to production.
 * - `RolesAndPermissionsSeeder::run()` — uses `RoleHelper::ROLE_NAMES`
 *   which this branch also corrects, so post-migration + post-seed
 *   rows are spelled correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'Impact_Zonal_Coordinator') // typo (no second 'o')
            ->where('guard_name', 'web')
            ->update(['name' => 'Impact_Zonal_Coordinator']); // corrected
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'Impact_Zonal_Coordinator') // corrected
            ->where('guard_name', 'web')
            ->update(['name' => 'Impact_Zonal_Coordinator']); // back to typo
    }
};
