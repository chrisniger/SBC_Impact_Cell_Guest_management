<?php
// Project-root relative — __DIR__ is scripts/. Autoload comes from there.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ImpactCell;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

$log = fopen(__DIR__ . '/../storage/logs/phase18_repro.log', 'w');
fwrite($log, "===== run started " . date('c') . " =====\n");

// Belt-and-suspenders Spatie cache clear (matches what test 4 setUp does).
app(PermissionRegistrar::class)->forgetCachedPermissions();

DB::listen(function ($q) use ($log) {
    fwrite($log, "  SQL: " . $q->sql . " | bindings: " . json_encode($q->bindings) . "\n");
});

try {
    $cell = ImpactCell::create([
        'id' => (string) Str::uuid(),
        'name' => 'Repro Soft Deleted Lead',
        'is_primary' => true,
    ]);
    fwrite($log, "S1 impact_cells INSERT OK id={$cell->id}\n");

    $former = User::factory()->create([
        'name' => 'Test Leader',
        'email' => 'former-repro@impact.test',
        'password' => Hash::make('whatever'),
        'impact_cell_id' => $cell->id,
        'active_role' => 'Impact_Leaders',
    ]);
    $former->assignRole('Impact_Leaders');
    fwrite($log, "S2 former INSERT OK id={$former->id}, roles=" . json_encode($former->getRoleNames()->all()) . "\n");

    $former->delete();
    $fresh = User::withTrashed()->find($former->id);
    fwrite($log, "S3 former soft-delete OK deleted_at={$fresh->deleted_at}\n");

    $data = [
        'name' => 'New Leader',
        'email' => 'newafter-repro@impact.test',
        'password' => 'Password123!',
        'roles' => ['Impact_Leaders'],
        'active_role' => 'Impact_Leaders',
        'impact_cell_id' => $cell->id,
    ];

    DB::beginTransaction();
    fwrite($log, "T0 outer DB::beginTransaction (simulating RefreshDatabase)\n");

    DB::transaction(function () use ($data, $log) {
        fwrite($log, "T1 inside DB::transaction\n");

        ImpactCell::where('id', $data['impact_cell_id'])->lockForUpdate()->first();
        fwrite($log, "T2 lockForUpdate OK\n");

        $occ = User::query()
            ->where('impact_cell_id', $data['impact_cell_id'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'Impact_Leaders'))
            ->whereNull('deleted_at')
            ->exists();
        fwrite($log, "T3 recheck occupied=" . ($occ ? 'YES' : 'NO') . "\n");

        if ($occ) {
            throw ValidationException::withMessages(['impact_cell_id' => 'repro: occupied']);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'impact_cell_id' => $data['impact_cell_id'] ?? null,
        ]);
        fwrite($log, "T4 User::create OK id={$user->id}, rowInDb=" . DB::table('users')->where('id', $user->id)->count() . "\n");

        $user->syncRoles($data['roles']);
        fwrite($log, "T5 syncRoles OK roles=" . json_encode($user->getRoleNames()->all()) . "\n");

        $user->forceFill(['active_role' => $data['active_role']])->save();
        fwrite($log, "T6 forceFill.save OK, active_role=" . User::find($user->id)->active_role . "\n");

        if (in_array('Impact_Leaders', $data['roles'], true) && ! empty($data['impact_cell_id'])) {
            $rows = ImpactCell::where('id', $data['impact_cell_id'])->update([
                'leader_name' => null, 'leader_phone' => null,
                'assistant_name' => null, 'assistant_phone' => null,
                'welfare_officer_name' => null, 'welfare_officer_phone' => null,
            ]);
            fwrite($log, "T7 ImpactCell::update OK rows={$rows}\n");
        }
        fwrite($log, "T8 returning user — savepoint will release\n");
        // Probe: is the user row STILL visible WITHIN the savepoint right now?
        $stillThere = DB::table('users')->where('id', $user->id)->count();
        fwrite($log, "T8b probe user row in savepoint={$stillThere}\n");
    });

    fwrite($log, "T9 transaction closed OK\n");
    DB::commit();
    fwrite($log, "T10 outer commit OK\n");

    $exists = DB::table('users')->where('email', $data['email'])->exists();
    $count  = DB::table('users')->where('email', $data['email'])->count();
    fwrite($log, "T11 newafter exists={$exists} count={$count}\n");
} catch (\Throwable $t) {
    fwrite($log, "CAUGHT " . get_class($t) . ": " . $t->getMessage() . "\n");
    fwrite($log, "  at " . $t->getFile() . ":" . $t->getLine() . "\n");
    foreach (array_slice($t->getTrace(), 0, 8) as $f) {
        fwrite($log, "  frame: " . ($f['file'] ?? '?') . ":" . ($f['line'] ?? '?') . " -> " . ($f['function'] ?? '?') . "\n");
    }
}
fclose($log);
echo "REPRO OK — log at storage/logs/phase18_repro.log\n";
