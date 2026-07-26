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
                'password'   => bcrypt('//Zonal##101'),
                'active_role' => 'Impact_Zonal_Cordinator',
            ],
        );

        $user->assignRole('Impact_Zonal_Cordinator');

        // Ensure active_role is set even if user already existed without it.
        if ($user->active_role !== 'Impact_Zonal_Cordinator') {
            $user->update(['active_role' => 'Impact_Zonal_Cordinator']);
        }
    }
}
