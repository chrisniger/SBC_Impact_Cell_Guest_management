<?php

namespace Database\Seeders;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Phase 06 — Follow Up Team test fixtures.
 *
 * Idempotent: `firstOrCreate` on users, marker-guarded guest fixtures.
 *
 * Seeds:
 *   - team1@impact.test / //Team##101  (Follow_UP)
 *       No personal guests (team sees all guests).
 *   - teamViewOnly@impact.test / //ViewOnly##101  (Follow_UP_View_Only)
 *       No personal guests (read-only team view).
 *
 * Also extends the officer1 fixtures with follow_up_status values so the
 * team dashboard KPIs are verifiable.
 */
class FollowUpTeamSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'Follow_UP', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Follow_UP_View_Only', 'guard_name' => 'web']);

        $team1 = User::firstOrCreate(
            ['email' => 'team1@impact.test'],
            [
                'name'        => 'Phase 06 Team Member',
                'password'    => '//Team##101',
                'active_role' => 'Follow_UP',
            ],
        );
        $team1->syncRoles(['Follow_UP']);
        $team1->forceFill(['active_role' => 'Follow_UP'])->save();

        $viewOnly = User::firstOrCreate(
            ['email' => 'teamViewOnly@impact.test'],
            [
                'name'        => 'Phase 06 View Only',
                'password'    => '//ViewOnly##101',
                'active_role' => 'Follow_UP_View_Only',
            ],
        );
        $viewOnly->syncRoles(['Follow_UP_View_Only']);
        $viewOnly->forceFill(['active_role' => 'Follow_UP_View_Only'])->save();

        Guest::where('guest_name', 'like', 'Team Fixture #%')->forceDelete();

        $officer1 = User::where('email', 'officer1@impact.test')->first();

        $teamFixtures = [
            ['follow_up_status' => null,               'contacted_status' => null],
            ['follow_up_status' => 'NOT CONTACTED',    'contacted_status' => null],
            ['follow_up_status' => 'CONTACTED',        'contacted_status' => 'Contacted'],
            ['follow_up_status' => 'WRONG NUMBER',     'contacted_status' => 'Wrong Number'],
            ['follow_up_status' => 'NOT REACHABLE',    'contacted_status' => 'Not Reachable'],
        ];

        foreach ($teamFixtures as $i => $fixture) {
            Guest::create([
                'guest_name'         => 'Team Fixture #' . ($i + 1),
                'follow_up_status'   => $fixture['follow_up_status'],
                'contacted_status'   => $fixture['contacted_status'],
                'follow_officer_id'  => $officer1?->id,
            ]);
        }
    }
}
