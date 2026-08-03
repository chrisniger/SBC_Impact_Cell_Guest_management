<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class ZonalCoordinatorSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'zonal1@impact.test'],
            [
                'name'       => 'Zonal Coordinator One',
                'password'   => '//Zonal##101', // hashed by User::casts() on assignment
                'active_role' => 'Impact_Zonal_Coordinator',
            ],
        );

        $user->assignRole('Impact_Zonal_Coordinator');

        // NOTE (orphan-role hardening round, 2026-08-03): the pre-Phase-14
        // "repair typo'd active_role" branch was REMOVED here. That state
        // (users.active_role === 'Impact_Zonal_Cordinator') can no longer
        // exist on any migrated DB: migration
        // 2026_08_03_100000_repair_orphan_impact_zonal_typo_role.php normalises
        // users.active_role in every DB state (fresh, re-imported, or orphaned).
        // Keeping the branch would have been dead code referencing the typo.
    }
}
