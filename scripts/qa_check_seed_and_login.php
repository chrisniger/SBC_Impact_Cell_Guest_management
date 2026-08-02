<?php

/**
 * QA check: post-recovery DB fixture state + a real login attempt.
 * Verifies the canonical seeders restored the login credentials after
 * the test-suite wipe (Phase 25/27/30 recovery contract).
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Guest;
use App\Models\ImpactCell;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

echo "DB: " . config('database.default') . ' / ' . config('database.connections.' . config('database.default') . '.database') . PHP_EOL;
echo "Users: " . User::count() . PHP_EOL;
foreach (User::all(['id', 'name', 'email', 'active_role']) as $u) {
    echo "  - {$u->id} {$u->email} [{$u->active_role}]\n";
}
echo "Roles: " . Role::count() . PHP_EOL;
echo "Guests: " . Guest::count() . PHP_EOL;
echo "Cells: " . ImpactCell::count() . PHP_EOL;

// Login credential check — verify the seeded admin password still matches.
$admin = User::where('email', 'sbcadmin@impact.test')->first();
if ($admin) {
    echo "ADMIN PASSWORD CHECK (//Chris##101): " .
        (Hash::check('//Chris##101', $admin->password) ? 'OK — login will work' : 'MISMATCH — login will fail') . PHP_EOL;
} else {
    echo "ADMIN MISSING — login cannot work\n";
}
