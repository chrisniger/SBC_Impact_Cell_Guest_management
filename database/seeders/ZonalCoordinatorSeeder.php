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

        // Ensure active_role is set even if user already existed without it.
        if ($user->active_role === 'Impact_Zonal_Cordinator') {
            // Repair only the known pre-Phase-14 fixture state. Do not
            // reset a healthy user's password merely because an admin
            // changed their active role after signup.
            $user->update([
                'password' => '//Zonal##101',
                'active_role' => 'Impact_Zonal_Coordinator',
            ]);
        }
    }
}
