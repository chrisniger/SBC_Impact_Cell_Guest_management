<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Default admin user — `sbcAdmin` (per Implementation/02_Database_Schema.md § AdminUser).
 *
 * Password is the literal from the design doc. Note: the `password` column
 * is hashed automatically by the `'hashed'` cast in `User::casts()`.
 * firstOrCreate → Eloquent::create → new static($attrs) → fill($attrs) →
 * setAttribute() — the cast fires on attribute set, BEFORE save() runs.
 * So we pass the plaintext password and let the cast do its job.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'sbcadmin@impact.test'],
            [
                'name'        => 'SBC Admin',
                'password'          => '//Chris##101',     // auto-hashed by the 'hashed' cast on setAttribute
                'active_role'        => 'Administrator',
                'email_verified_at'  => now(),
            ],
        );

        if (! $admin->hasRole('Administrator')) {
            $admin->assignRole('Administrator');
        }

        // Defend against a stale `active_role` from a prior seed where the
        // user existed but had no role assigned yet — `$fillable` includes
        // `active_role`, so update() works without forceFill.
        // Note we read `$admin->active_role` (raw column), NOT `$admin->activeRole()`
        // (resolved accessor), because the intent here is to inspect the persisted
        // column value for staleness — the accessor would already return
        // 'Administrator' via fallback even if the column was null.
        if ($admin->active_role !== 'Administrator' || $admin->email_verified_at === null) {
            $admin->update([
                'active_role'       => 'Administrator',
                'email_verified_at' => $admin->email_verified_at ?? now(),
            ]);
        }
    }
}
