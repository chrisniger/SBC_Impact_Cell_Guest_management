<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed the 9 v2 roles from Implementation/03_Three_User_Groups.md.
 * Guard name is `web` (standard for Inertia/Breeze session auth).
 *
 * IMPORTANT: forgetCachedPermissions() must be called BEFORE any role
 * changes; otherwise the existing Spatie cache obscures the new rows
 * and $user->hasRole() returns stale results for the rest of the request.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** @var list<string> */
    private const ROLES = [
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

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $name) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
            );
        }

        // Clear again after seeding so any cached "role doesn't exist"
        // state from a prior run is purged.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
