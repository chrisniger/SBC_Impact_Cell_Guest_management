<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Harden the Phase 14 zonal-role typo fix so a DB re-import can never
 * reintroduce the orphaned `Impact_Zonal_Cordinator` row.
 *
 * Background / why this migration exists
 * --------------------------------------
 * The Phase 14 fix for the one-character typo
 * (`Impact_Zonal_Cordinator` -> `Impact_Zonal_Coordinator`) shipped as a
 * RENAME-only data migration (`2026_07_31_150000_fix_impact_zonal_typo_in_roles_table.php`).
 * A rename-only `up()` is fragile in two real-world scenarios:
 *
 *   1. The migration runs BEFORE the typo'd row exists (e.g. a DB that was
 *      restored from an older dump, then migrated). The `WHERE name = 'Impact_Zonal_Cordinator'`
 *      UPDATE selects 0 rows and silently does nothing — but the migration is
 *      now marked "Ran", so it will NEVER re-run on that DB.
 *   2. A later `RolesAndPermissionsSeeder::run()` (firstOrCreate on the
 *      CORRECT spelling) then creates a NEW `Impact_Zonal_Coordinator` row,
 *      while the old typo'd row survives as an orphan under its own id.
 *
 * That exact state bit the dev environment on 2026-08-03: `/register` 503'd
 * because `RegisteredUserController::ensureSignupRolesSeeded()` aborts when
 * any `RoleHelper::SIGNUP_VISIBLE_ROLES` row is missing — and the roles table
 * only had the typo'd row, not the correctly-spelled one the guard reads.
 *
 * A shipped migration must never be edited in place (installations that
 * already ran it would never see the change), so this follow-up migration is
 * the canonical, idempotent repair. It handles EVERY possible DB state:
 *
 *   State A — clean (no typo row):                no-op (plus any users.active_role drift normalised).
 *   State B — typo row only (no correct row):     rename typo -> correct (the original Phase 14 intent).
 *   State C — BOTH rows exist (the orphan state): repoint/drop model_has_roles assignments,
 *                                                 drop role_has_permissions grants, delete the orphan row.
 *
 * Idempotency
 * -----------
 * All three branches converge on the same postcondition:
 *   - exactly one `Impact_Zonal_Coordinator` row on guard `web`
 *   - zero `Impact_Zonal_Cordinator` rows
 *   - no model_has_roles / role_has_permissions rows referencing a deleted id
 *   - no `users.active_role` holding the typo'd string
 * Re-running on an already-repaired DB hits State A and is a no-op.
 *
 * Note on `role_has_permissions` vs `model_has_permissions`: only the former
 * keys on `role_id` (Spatie schema). `model_has_permissions` has no `role_id`
 * column, so it needs no cleanup here.
 *
 * `down()` is a deliberate no-op — reversing a data repair would mean
 * re-creating a typo'd role row and re-assigning users to it, which is
 * destructive and pointless. (Same precedent as the flatten-all-cells
 * migration's `down()`.)
 */
return new class extends Migration
{
    public function up(): void
    {
        $typoName = 'Impact_Zonal_Cordinator';   // misspelled (missing second 'o')
        $correctName = 'Impact_Zonal_Coordinator';
        $guard = 'web';

        // Normalise users.active_role drift in EVERY state — a re-imported DB
        // may carry user rows whose active_role still holds the typo string.
        DB::table('users')
            ->where('active_role', $typoName)
            ->update(['active_role' => $correctName]);

        $typo = DB::table('roles')
            ->where('name', $typoName)
            ->where('guard_name', $guard)
            ->first();
        $correct = DB::table('roles')
            ->where('name', $correctName)
            ->where('guard_name', $guard)
            ->first();

        // State A — clean. Nothing to do.
        if (! $typo) {
            return;
        }

        // State B — typo row only: rename it (the original Phase 14 intent).
        if (! $correct) {
            DB::table('roles')
                ->where('id', $typo->id)
                ->update(['name' => $correctName]);

            return;
        }

        // State C — both rows exist (the orphan state that 503'd /register).
        // 1) Repoint (or drop-as-redundant) every model_has_roles assignment.
        $assignments = DB::table('model_has_roles')->where('role_id', $typo->id)->get();
        foreach ($assignments as $a) {
            $alreadyHasCorrect = DB::table('model_has_roles')
                ->where('role_id', $correct->id)
                ->where('model_id', $a->model_id)
                ->where('model_type', $a->model_type)
                ->exists();

            if ($alreadyHasCorrect) {
                // Same model already holds the correct role — the typo
                // assignment is redundant; drop it.
                DB::table('model_has_roles')
                    ->where('role_id', $a->role_id)
                    ->where('model_id', $a->model_id)
                    ->where('model_type', $a->model_type)
                    ->delete();
            } else {
                // Model only had the typo role — repoint to the correct one.
                DB::table('model_has_roles')
                    ->where('role_id', $a->role_id)
                    ->where('model_id', $a->model_id)
                    ->where('model_type', $a->model_type)
                    ->update(['role_id' => $correct->id]);
            }
        }

        // 2) Drop permission grants bound to the orphan (role_has_permissions
        //    keys on role_id — the grants must not dangle after the delete).
        //    Repointing them onto the correct role was considered and rejected:
        //    role_has_permissions PK is (permission_id, role_id), so a repoint
        //    could collide with grants the correct role already holds. Dropping
        //    is the safe, intent-preserving choice for a role that should never
        //    have existed (both rows currently carry 0 grants).
        DB::table('role_has_permissions')->where('role_id', $typo->id)->delete();

        // 3) Delete the orphan row. (The unique index on name+guard_name
        //    prevents renaming it onto the already-correct spelling.)
        DB::table('roles')->where('id', $typo->id)->delete();
    }

    public function down(): void
    {
        // Deliberate no-op — see docblock. Reversing a data repair (recreating
        // a typo'd role row and re-assigning users to it) is destructive and
        // provides no value; the repair itself is idempotent and safe to keep.
    }
};
