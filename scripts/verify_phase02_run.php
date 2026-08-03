<?php
// Standalone Phase 02 verifier. Run with:  php scripts/verify_phase02_run.php
// Avoids the bash + tinker stdin-echo gotcha by bootstrapping Laravel directly
// and printing to STDOUT.

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) {
        echo "  PASS  $label" . ($detail ? "  ($detail)" : '') . "\n";
        $pass++;
    } else {
        echo "  FAIL  $label" . ($detail ? "  ($detail)" : '') . "\n";
        $fail++;
    }
}

echo "=== Phase 02 verification ===\n\n";

// 1. Role count + names
echo "[1] roles seeded\n";
$count = Role::count();
    check("10 roles exist", $count === 10, "got {$count}");

// Regression guard (1b): every key in GROUP_GUEST_OWNER must be snake_case.
// Prevents recurrence of the post-Phase-05 silent-data-loss bug (matrix was
// camelCase, HTTP body is snake_case → stripDisallowed dropped every
// multi-word field from FollowUpOfficer writes).
foreach (Illuminate\Support\Arr::flatten(RoleHelper::GROUP_GUEST_OWNER) as $field) {
    check("[1b] matrix key '{$field}' is snake_case",
        (bool) preg_match('/^[a-z][a-z0-9_]*$/', $field),
        'camelCase or non-snake_case keys silently strip fields in production');
}

// Derive from RoleHelper::ROLE_NAMES (the single source of truth per its own
// docblock) instead of a hardcoded literal. The hardcoded list is exactly what
// went stale in Phase 14: the codebase fixed the 'Impact_Zonal_Cordinator' ->
// 'Impact_Zonal_Coordinator' spelling, but this list kept the typo — and only
// passed because the DB still carried the typo row. Hardening round 2026-08-03:
// derive + sort BOTH sides so no spelling drift can ever pass this gate again.
$expectedNames = collect(RoleHelper::ROLE_NAMES)->sort()->values()->toArray();
$actualNames = Role::pluck('name')->sort()->values()->toArray();
check("role names match RoleHelper::ROLE_NAMES (alphabetic)", $actualNames === $expectedNames, "got [" . implode(',', $actualNames) . "]");

// 2. Admin user
echo "\n[2] sbcadmin user\n";
$admin = User::where('email', 'sbcadmin@impact.test')->first();
check("admin exists", $admin !== null);
if ($admin !== null) {
    check("admin has Administrator role",
        $admin->hasRole('Administrator'));
    check("admin active_role = Administrator",
        $admin->active_role === 'Administrator',
        "got " . var_export($admin->active_role, true));
    check("admin activeRole() = Administrator",
        $admin->activeRole() === 'Administrator',
        "got " . var_export($admin->activeRole(), true));
    check("admin activeGroup() = null (Admin = no group)",
        $admin->activeGroup() === null,
        "got " . var_export($admin->activeGroup(), true));
    check("admin canSwitchTo(Administrator) = true",
        $admin->canSwitchTo('Administrator') === true);
    check("admin canSwitchTo('NotARole') = false",
        $admin->canSwitchTo('NotARole') === false);
    check("admin canSwitchTo(null) = false",
        $admin->canSwitchTo(null) === false);
}

// 3. RoleHelper::groupOf matrix
echo "\n[3] RoleHelper::groupOf\n";
$cases = [
    ['Administrator',                  null],
    ['Supervisor',                     null],
    ['Impact_Leaders',                 'impactCell'],
    ['Impact_Cell_Admin',              'impactCell'],
    ['Impact_Cell_Report',             'impactCell'],
    ['FollowUpOfficer',                'followUpOfficer'],
    ['Follow_UP_Admin',                'followUpOfficer'],
    ['Follow_UP',                      'followUpTeam'],
    ['Follow_UP_View_Only',            'followUpTeam'],
    ['',                               null],
    ['NoSuchRole',                     null],
];
foreach ($cases as [$role, $expected]) {
    $got = RoleHelper::groupOf($role);
    check("groupOf('{$role}') = " . var_export($expected, true), $got === $expected,
        'got ' . var_export($got, true));
}

// 4. RoleHelper::canEditField policy
//    Keys are snake_case to match the production HTTP wire format
//    (per RoleHelper::GROUP_GUEST_OWNER — single source of truth).
echo "\n[4] RoleHelper::canEditField column policy\n";
$policyCases = [
    ['Administrator',     'comments',     true],
    ['Administrator',     'unknownField', true],
    ['FollowUpOfficer',   'comments',     true],
    ['FollowUpOfficer',   'follow_up_status', false],
    ['FollowUpOfficer',   'impact_status', false],
    ['Follow_UP',         'follow_up_status', true],
    ['Follow_UP',         'phone',        false],
    ['Impact_Leaders',    'impact_status', true],
    ['Supervisor',        'comments',     false],
    [null,                'comments',     false],
    ['NoSuchRole',        'phone',        false],
];
foreach ($policyCases as [$role, $field, $expected]) {
    $got = RoleHelper::canEditField($role, $field);
    $r = $role ?? 'null';
    check("canEditField({$r}, '{$field}') = " . ($expected ? 'true' : 'false'),
        $got === $expected, 'got ' . var_export($got, true));
}

// 5. stripDisallowed
//    Body keys are snake_case to match the production HTTP wire format.
echo "\n[5] RoleHelper::stripDisallowed\n";
$body = ['name' => 'A', 'phone' => '555', 'comments' => 'hi', 'impact_status' => 'pending', 'follow_up_status' => 'pending'];
$strippedAdmin = RoleHelper::stripDisallowed('Administrator', $body);
check("Admin: pass-through", $strippedAdmin === $body, 'got ' . json_encode($strippedAdmin));

$strippedOfficer = RoleHelper::stripDisallowed('FollowUpOfficer', $body);
// `name` is intentionally NOT in the per-group matrix — it's set at create-time by Impact Cell Leaders.
$expectedOfficer = ['phone' => '555', 'comments' => 'hi'];
check("FollowUpOfficer keeps ONLY officer fields (drops name/impact_status/follow_up_status)",
    $strippedOfficer === $expectedOfficer,
    'got ' . json_encode($strippedOfficer));

$strippedNoGroup = RoleHelper::stripDisallowed('Supervisor', $body);
check("Supervisor (no group): drops everything defensively",
    $strippedNoGroup === [],
    'got ' . json_encode($strippedNoGroup));

$strippedNull   = RoleHelper::stripDisallowed(null, $body);
check("null role: drops everything", $strippedNull === [], 'got ' . json_encode($strippedNull));

// 6. switch-role route
echo "\n[6] /auth/switch-role route registered\n";
$routes = collect(Route::getRoutes())
    ->filter(fn ($r) => str_contains($r->uri(), 'auth/switch-role'));
check("route exists", $routes->count() > 0);
foreach ($routes as $r) {
    $action = $r->getActionName() ?: 'Closure';
    check("route action is App\\Http\\Controllers\\Auth\\RoleSwitchController@store",
        str_contains($action, 'RoleSwitchController@store'),
        'got ' . $action);
}

// 7. allGroupOwnedFields union is non-empty
echo "\n[7] RoleHelper::allGroupOwnedFields\n";
$all = RoleHelper::allGroupOwnedFields();
check("union >= 10 field names", count($all) >= 10, 'got ' . count($all));
check("includes impact_status, follow_up_status, phone, gender",
    in_array('impact_status', $all) && in_array('follow_up_status', $all)
    && in_array('phone', $all) && in_array('gender', $all));

echo "\n=== Summary: $pass pass / $fail fail ===\n";
exit($fail === 0 ? 0 : 1);
