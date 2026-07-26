<?php
// Phase 02 end-to-end verification.
// Run with:  php artisan tinker < scripts/verify_phase02.php
// (stdin pipe keeps backslash namespace refs intact — avoids bash quote-eating.)

use App\Models\User;
use App\Support\RoleHelper;
use Spatie\Permission\Models\Role;

echo "=== 1. role count ===\n";
$count = Role::count();
echo "  count = {$count}\n" . ($count === 9 ? "  PASS (9 roles)\n" : "  FAIL (expected 9)\n");

echo "\n=== 2. role names ===\n";
foreach (Role::orderBy('name')->get() as $r) {
    echo "  - {$r->name}\n";
}

echo "\n=== 3. admin user ===\n";
$admin = User::where('email', 'sbcadmin@impact.test')->first();
if ($admin === null) {
    echo "  FAIL (no admin row)\n";
} else {
    echo "  email       = {$admin->email}\n";
    echo "  active_role = " . ($admin->active_role ?? 'NULL') . "\n";
    echo "  roles       = [" . $admin->getRoleNames()->implode(',') . "]\n";
    echo "  activeRole()       = " . ($admin->activeRole() ?? 'NULL') . "\n";
    echo "  activeGroup()      = " . ($admin->activeGroup() ?? 'NULL') . "\n";
    echo "  hasRole(Administrator) = " . ($admin->hasRole('Administrator') ? 'YES' : 'NO') . "\n";
    echo "  canSwitchTo(Administrator) = " . ($admin->canSwitchTo('Administrator') ? 'YES' : 'NO') . "\n";
}

echo "\n=== 4. RoleHelper::groupOf matrix ===\n";
$cases = [
    'Administrator'                  => null,
    'Supervisor'                     => null,
    'Impact_Leaders'                 => 'impactCell',
    'Impact_Cell_Admin'              => 'impactCell',
    'Impact_Cell_Report'             => 'impactCell',
    'FollowUpOfficer'                => 'followUpOfficer',
    'Follow_UP_Admin'                => 'followUpOfficer',
    'Follow_UP'                      => 'followUpTeam',
    'Follow_UP_View_Only'            => 'followUpTeam',
    ''                               => null,
    'NoSuchRole'                     => null,
];
$ok = true;
foreach ($cases as $role => $expected) {
    $got = RoleHelper::groupOf($role);
    $pass = ($got === $expected);
    if (! $pass) {
        $ok = false;
    }
    echo sprintf("  %-25s => %-18s  (expected %s)  %s\n",
        "'{$role}'",
        $got === null ? 'null' : "'{$got}'",
        $expected === null ? 'null' : "'{$expected}'",
        $pass ? 'PASS' : 'FAIL'
    );
}
echo $ok ? "  PASS (all 11 cases)\n" : "  FAIL (some cases)\n";

echo "\n=== 5. canEditField column policy ===\n";
$policyCases = [
    ['Administrator',     'comments',     true],
    ['Administrator',     'unknownField', true],
    ['FollowUpOfficer',   'comments',     true],
    ['FollowUpOfficer',   'impactStatus', false],  // impactCell owns it
    ['FollowUpOfficer',   'followUpStatus', false], // followUpTeam owns it
    ['Follow_UP',         'followUpStatus', true],
    ['Follow_UP',         'phone',        false],   // officer owns it
    ['Impact_Leaders',    'impactStatus', true],
    ['Supervisor',        'comments',     false],   // no group
    [null,                'comments',     false],
];
$okPol = true;
foreach ($policyCases as [$role, $field, $expected]) {
    $got = RoleHelper::canEditField($role, $field);
    $pass = ($got === $expected);
    if (! $pass) {
        $okPol = false;
    }
    echo sprintf("  canEditField(%-15s, %-15s) = %-5s (expected %s)  %s\n",
        $role === null ? 'null' : "'{$role}'",
        "'{$field}'",
        $got ? 'true' : 'false',
        $expected ? 'true' : 'false',
        $pass ? 'PASS' : 'FAIL'
    );
}
echo $okPol ? "  PASS (all 10 cases)\n" : "  FAIL (some cases)\n";

echo "\n=== 6. switch-role route registered ===\n";
$routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
    ->filter(fn ($r) => str_contains($r->uri(), 'auth/switch-role'));
if ($routes->count() > 0) {
    foreach ($routes as $r) {
        echo "  " . implode('|', $r->methods()) . " /" . $r->uri() . " => " . $r->getActionName() . "  PASS\n";
    }
} else {
    echo "  FAIL (no /auth/switch-role route)\n";
}

echo "\n=== Phase 02 verification END ===\n";
