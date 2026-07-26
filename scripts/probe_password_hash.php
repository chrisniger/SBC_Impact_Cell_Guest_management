<?php
// Phase 02 password-hash probe.
//
// Run with:  php scripts/probe_password_hash.php
//
// Probes whether the `'hashed'` cast on User::password fired during the
// initial seed (i.e. whether the stored hash is a real bcrypt $2y$ hash
// despite AdminUserSeeder passing the plaintext password to firstOrCreate).
//
// Exits 0 = cast fired correctly. Exits 1 = cast is broken (stored hash
// is plaintext or wrong algorithm).

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$admin = User::where('email', 'sbcadmin@impact.test')->first();

if ($admin === null) {
    fwrite(STDERR, "FAIL: sbcadmin@impact.test not found in DB. Run `php artisan db:seed --force` first.\n");
    exit(1);
}

$hash = $admin->password;
$len  = strlen($hash);
$isBcrypt = $len === 60 && str_starts_with($hash, '$2y$');
$checkPasses = Hash::check('//Chris##101', $hash);
$hasPlaintext = str_contains($hash, 'Chris');

echo "stored hash length : {$len}\n";
echo "starts with \$2y\$  : " . ($isBcrypt ? 'YES (bcrypt)' : 'NO') . "\n";
echo "Hash::check pass   : " . ($checkPasses ? 'YES (cast worked)' : 'NO (cast broken)') . "\n";
echo "plaintext leak     : " . ($hasPlaintext ? 'LEAK!' : 'clean') . "\n";
echo "first 20 chars     : " . substr($hash, 0, 20) . "...\n";

if (! $isBcrypt || ! $checkPasses || $hasPlaintext) {
    fwrite(STDERR, "\nFAIL: 'hashed' cast did not fire correctly during seed.\n");
    fwrite(STDERR, "      Check: app/Models/User.php casts() includes 'password' => 'hashed'\n");
    fwrite(STDERR, "             database/seeders/AdminUserSeeder.php does NOT call Hash::make()\n");
    exit(1);
}

echo "\nPASS: 'hashed' cast fired correctly — bcrypt $2y$ hash stored, plaintext password verifies.\n";
exit(0);