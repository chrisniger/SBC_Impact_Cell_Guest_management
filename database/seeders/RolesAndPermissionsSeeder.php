<?php

namespace Database\Seeders;

use App\Support\RoleHelper;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed the 10 v2 roles from Implementation/03_Three_User_Groups.md.
 * Guard name is `web` (standard for Inertia/Breeze session auth).
 *
 * The role names are sourced from `App\Support\RoleHelper::ROLE_NAMES`
 * — the single source of truth shared with `AdminUserRequest`
 * validation, the `Admin\UserController::addableRoles()` picker,
 * and any future role-aware code. If you need to add or rename a role,
 * update the constant there and re-run this seeder; everything else
 * follows automatically.
 *
 * IMPORTANT: forgetCachedPermissions() must be called BEFORE any role
 * changes; otherwise the existing Spatie cache obscures the new rows
 * and $user->hasRole() returns stale results for the rest of the request.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleHelper::ROLE_NAMES as $name) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
            );
        }

        // Clear again after seeding so any cached "role doesn't exist"
        // state from a prior run is purged.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
