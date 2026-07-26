<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Phase 05 — Follow Up Officer test fixtures.
 *
 * Idempotent: `firstOrCreate` + `forceDelete` on prior guest rows before
 * re-creating, so re-running this seeder leaves exactly the same state.
 *
 * Seeds:
 *   - officer1@impact.test / //Officer##101  (FollowUpOfficer)
 *       5 assigned guests spanning all 5 `contacted_status` permutations:
 *         1. null  ← "NOT CONTACTED" bucket (pending contact)
 *         2. 'No'  ← "NOT CONTACTED" bucket (pending contact)
 *         3. 'Contacted'  ← "CONTACTED" bucket (contacted, but no visit yet)
 *         4. 'AvailableForVisit'  ← "VISIT READY" bucket (visit pending)
 *         5. 'Visited' ← "VISITED" bucket (visited = true)
 *       Expected KPIs from these 5:
 *         - pendingContacts = 2 (null + 'No')
 *         - totalCalls      = 4 (anything with a non-null/non-empty contact)
 *         - visited         = 1 (only #5)
 *         - pendingVisit    = 1 (only #4)
 *         - responseRate    = round(1/4 * 100, 1) = 25.0
 *   - followUpAdmin@impact.test / //Admin##101  (Follow_UP_Admin)
 *       No guests out of the box — this user's value is the REASSIGN
 *       permission (asserted by the verifier directly).
 */
class FollowUpOfficerSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist (no-op if RolesAndPermissionsSeeder already ran).
        Role::firstOrCreate(['name' => 'FollowUpOfficer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Follow_UP_Admin',  'guard_name' => 'web']);

        // ── Officer 1: 5-guest fixture ───────────────────────────────────
        $officer1 = User::firstOrCreate(
            ['email' => 'officer1@impact.test'],
            [
                'name'        => 'Phase 05 Officer One',
                'password'    => '//Officer##101',
                'active_role' => 'FollowUpOfficer',
            ],
        );
        $officer1->syncRoles(['FollowUpOfficer']);
        $officer1->forceFill(['active_role' => 'FollowUpOfficer'])->save();

        // Wipe PRIOR fixture guests (idempotency) WITHOUT touching any
        // real guests an admin may have assigned to officer1 between
        // re-runs. The marker is `guest_name LIKE 'Officer1 Guest #%'`
        // (set by this seeder below).
        Guest::where('follow_officer_id', $officer1->id)
            ->where('guest_name', 'like', 'Officer1 Guest #%')
            ->forceDelete();

        $fixtures = [
            ['status' => null,               'visited' => false],
            ['status' => 'No',               'visited' => false],
            ['status' => 'Contacted',        'visited' => false],
            ['status' => 'AvailableForVisit','visited' => false],
            ['status' => 'Visited',          'visited' => true],
        ];

        foreach ($fixtures as $i => $fixture) {
            Guest::create([
                'guest_name'        => "Officer1 Guest #" . ($i + 1),
                'follow_officer_id' => $officer1->id,
                'contacted_status'  => $fixture['status'],
                'visited'           => $fixture['visited'],
            ]);
        }

        // ── Officer 2: Follow_UP_Admin (can reassign) ─────────────────────
        $officer2 = User::firstOrCreate(
            ['email' => 'followUpAdmin@impact.test'],
            [
                'name'        => 'Phase 05 Follow Up Admin',
                'password'    => '//Admin##101',
                'active_role' => 'Follow_UP_Admin',
            ],
        );
        $officer2->syncRoles(['Follow_UP_Admin']);
        $officer2->forceFill(['active_role' => 'Follow_UP_Admin'])->save();
    }
}
