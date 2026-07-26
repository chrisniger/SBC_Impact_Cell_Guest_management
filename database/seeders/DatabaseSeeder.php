<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters: roles MUST be seeded before any user is created
     * so that assignRole() in AdminUserSeeder resolves to a real row.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            ImpactCellSeeder::class,
            FollowUpOfficerSeeder::class,
            FollowUpTeamSeeder::class,
            ZonalCoordinatorSeeder::class,
        ]);
    }
}
