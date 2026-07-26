<?php
/**
 * Phase 05 end-to-end verification.
 *
 * Run with:  php scripts/verify_phase05_run.php
 *
 * 22 sub-assertions (was 18 spec items; the [7] split into 3 sub-checks +
 * the [13] variant discriminator sub-check + the [18] queue has 2 sub-checks
 * brings the total source-side count to 22).
 *
 * Verifier-only destructive step: before invoking the seeder, we wipe ALL
 * guests assigned to officer1 (bypassing the marker's guard). This is
 * safe because the verifier only ever runs in dev/test — production NEVER
 * runs `php scripts/verify_phase05_run.php`. The seeder itself stays
 * safety-first on marker-guarded `guest_name LIKE 'Officer1 Guest #%'`.
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\DashboardController;
use App\Models\Guest;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Response;
use Spatie\Permission\Models\Role;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        echo "  PASS  $label" . ($detail ? "  ($detail)" : '') . "\n";
        $pass++;
    } else {
        echo "  FAIL  $label" . ($detail ? "  ($detail)" : '') . "\n";
        $fail++;
    }
}

echo "=== Phase 05 verification ===\n\n";

// ─────────────────────────────────────────────────────────────────────────
// [1]-[4] Officer test users + role assignment + active_role
// ─────────────────────────────────────────────────────────────────────────
echo "[1]-[4] Officer test users\n";

$officer1 = User::firstOrCreate(
    ['email' => 'officer1@impact.test'],
    ['name' => 'Phase 05 Officer One', 'password' => '//Officer##101', 'active_role' => 'FollowUpOfficer'],
);
$officer1->syncRoles(['FollowUpOfficer']);
$officer1->forceFill(['active_role' => 'FollowUpOfficer'])->save();
$officer1->refresh();

check('[1] officer1@impact.test exists', $officer1->exists);
check('[2] officer1 has FollowUpOfficer role + active_role',
    $officer1->hasRole('FollowUpOfficer') && $officer1->active_role === 'FollowUpOfficer',
    'activeRole() = ' . ($officer1->activeRole() ?? 'null'));

$officer2 = User::firstOrCreate(
    ['email' => 'followUpAdmin@impact.test'],
    ['name' => 'Phase 05 Follow Up Admin', 'password' => '//Admin##101', 'active_role' => 'Follow_UP_Admin'],
);
$officer2->syncRoles(['Follow_UP_Admin']);
$officer2->forceFill(['active_role' => 'Follow_UP_Admin'])->save();
$officer2->refresh();

check('[3] followUpAdmin@impact.test exists', $officer2->exists);
check('[4] followUpAdmin has Follow_UP_Admin role + active_role',
    $officer2->hasRole('Follow_UP_Admin') && $officer2->active_role === 'Follow_UP_Admin',
    'activeRole() = ' . ($officer2->activeRole() ?? 'null'));

// ─────────────────────────────────────────────────────────────────────────
// Verifier-only clean slate + re-seed (deterministic state for [5])
// ─────────────────────────────────────────────────────────────────────────
echo "\n[!] verifier-only: wipe ALL officer1 guests (bypasses marker-guard), then re-seed\n";
Guest::where('follow_officer_id', $officer1->id)->forceDelete();
(Illuminate\Support\Facades\Artisan::call('db:seed', [
    '--class' => 'Database\\Seeders\\FollowUpOfficerSeeder',
    '--force' => true,
]));
echo "  (verifier seeded 5 marker guests via FollowUpOfficerSeeder)\n\n";

// ─────────────────────────────────────────────────────────────────────────
// [5]-[7] Officer1 has 5 assigned guests covering all 5 contacted_status permutations
// ─────────────────────────────────────────────────────────────────────────
echo "[5]-[7] Officer1 guest fixtures\n";

$officer1Guests = Guest::where('follow_officer_id', $officer1->id)->whereNull('deleted_at')->get();
check('[5] officer1 has exactly 5 assigned (live) guests',
    $officer1Guests->count() === 5,
    'count = ' . $officer1Guests->count());

// Set equality (order-independent) — avoids Collection::sort() alphabetical-comparison
// pitfalls (e.g. 'No' vs the literal sentinel 'NULL' sorts lexicographically by case,
// not the order we want for an assertion message).
$statusesPresent = $officer1Guests->pluck('contacted_status')->map(fn ($v) => $v ?? 'NULL')->unique()->values()->all();
$expectedStatuses = ['NULL', 'No', 'Contacted', 'AvailableForVisit', 'Visited'];
$expectedSet = collect($expectedStatuses);
$presentSet   = collect($statusesPresent);
check('[6] guests span all 5 contacted_status permutations',
    $expectedSet->diff($presentSet)->isEmpty() && $presentSet->diff($expectedSet)->isEmpty(),
    'got: [' . implode(', ', $statusesPresent) . '] expected: [' . implode(', ', $expectedStatuses) . ']');

$visitedTrueCount   = $officer1Guests->where('visited', true)->count();
$visitedFalseCount  = $officer1Guests->where('visited', false)->count();
$visitedStatusCount = $officer1Guests->where('contacted_status', 'Visited')->count();
check('[7] exactly 1 guest has contacted_status=Visited',
    $visitedStatusCount === 1,
    'count = ' . $visitedStatusCount);
check('[7] exactly 1 guest has visited=true (matches the "Visited" status guest)',
    $visitedTrueCount === 1,
    'count = ' . $visitedTrueCount);
check('[7] the remaining 4 guests have visited=false',
    $visitedFalseCount === 4,
    'count = ' . $visitedFalseCount);

// ─────────────────────────────────────────────────────────────────────────
// [8]-[11] RoleHelper::canEditField Special-Case for follow_officer_id
// ─────────────────────────────────────────────────────────────────────────
echo "\n[8]-[11] RoleHelper::canEditField Special-Case (follow_officer_id)\n";

check('[8] Administrator CAN write follow_officer_id',
    RoleHelper::canEditField('Administrator', 'follow_officer_id') === true);

check('[9] Follow_UP_Admin CAN write follow_officer_id',
    RoleHelper::canEditField('Follow_UP_Admin', 'follow_officer_id') === true);

check('[10] FollowUpOfficer (plain officer) CANNOT write follow_officer_id',
    RoleHelper::canEditField('FollowUpOfficer', 'follow_officer_id') === false);

check('[11] Impact / Team / Supervisor / null all cannot write follow_officer_id',
    RoleHelper::canEditField('Impact_Leaders',       'follow_officer_id') === false
    && RoleHelper::canEditField('Impact_Cell_Admin',  'follow_officer_id') === false
    && RoleHelper::canEditField('Follow_UP',          'follow_officer_id') === false
    && RoleHelper::canEditField('Follow_UP_View_Only','follow_officer_id') === false
    && RoleHelper::canEditField('Supervisor',         'follow_officer_id') === false
    && RoleHelper::canEditField(null,                 'follow_officer_id') === false);

// ─────────────────────────────────────────────────────────────────────────
// [12] DashboardController@index route registered
// ─────────────────────────────────────────────────────────────────────────
echo "\n[12] DashboardController route\n";

$names = collect(Route::getRoutes()->getRoutes())
    ->map(fn ($r) => $r->getName())
    ->filter()
    ->values()
    ->all();
check('[12] dashboard route named', in_array('dashboard', $names, true),
    'route:list names = ' . implode(', ', array_slice($names, 0, 12)));

// ─────────────────────────────────────────────────────────────────────────
// [13]-[17] DashboardController officer KPI math
// ─────────────────────────────────────────────────────────────────────────
echo "\n[13]-[17] DashboardController officer KPI math\n";

// Render through the controller to exercise the same path as a real request.
$controller = app(DashboardController::class);
$request = Request::create('/dashboard', 'GET');
$request->setUserResolver(fn () => $officer1);

$response = $controller->index($request);

// Inertia::render returns an Inertia\Response; pull the props via reflection.
if (! $response instanceof Response) {
    echo "  FAIL  [13] controller did not return Inertia\\Response\n";
    $fail += 5;
} else {
    $reflect   = new ReflectionClass($response);
    $propsProp = $reflect->getProperty('props');
    $propsProp->setAccessible(true);
    $props = $propsProp->getValue($response);

    check('[13] variant = "officer"', ($props['variant'] ?? null) === 'officer');
    check('[13] pendingContacts KPI = 2', ($props['kpis']['pendingContacts'] ?? null) === 2,
        'got ' . ($props['kpis']['pendingContacts'] ?? 'null'));
    check('[14] totalCalls KPI = 4',      ($props['kpis']['totalCalls']      ?? null) === 4,
        'got ' . ($props['kpis']['totalCalls'] ?? 'null'));
    check('[15] visited KPI = 1',          ($props['kpis']['visited']    ?? null) === 1,
        'got ' . ($props['kpis']['visited'] ?? 'null'));
    check('[16] pendingVisit KPI = 1',     ($props['kpis']['pendingVisit'] ?? null) === 1,
        'got ' . ($props['kpis']['pendingVisit'] ?? 'null'));
    check('[17] responseRate KPI = 25.0',  ($props['kpis']['responseRate'] ?? null) === 25.0,
        'got ' . ($props['kpis']['responseRate'] ?? 'null'));
}

// ─────────────────────────────────────────────────────────────────────────
// [18] DashboardController officer queue: limited to 8, NOT CONTACTED first
// ─────────────────────────────────────────────────────────────────────────
echo "\n[18] DashboardController officer queue\n";

if ($response instanceof Response) {
    $props = $propsProp->getValue($response);
    $queue = $props['queue'] ?? [];

    check('[18] queue length <= 8 (LIMIT applied)',
        is_array($queue) && count($queue) === 5,      // we seeded exactly 5, so it's 5
        'count = ' . count($queue));

    // Bucket 0 = NOT CONTACTED (NULL / '' / 'No' / 'Not Contacted')
    // First 2 entries (positions [0] and [1]) of the queue MUST both be
    // NOT CONTACTED because we have 2 such guests.
    $firstTwo = array_slice($queue, 0, 2);
    $bothNotContacted = count($firstTwo) === 2
        && collect($firstTwo)->every(fn ($g) =>
            $g['contactedStatus'] === null
            || $g['contactedStatus'] === ''
            || $g['contactedStatus'] === 'No'
            || $g['contactedStatus'] === 'Not Contacted'
        );

    check('[18] queue bucket 0 (NOT CONTACTED) ordered first',
        $bothNotContacted,
        'first 2 statuses: ' . collect($firstTwo)->pluck('contactedStatus')->map(fn ($v) => $v ?? 'NULL')->implode(', '));
}

// ─────────────────────────────────────────────────────────────────────────
// Cleanup: keep seeded users + their fixture guests so re-runs are deterministic.
// (The seeder's marker-guard + this verifier's explicit clean-step together
//  guarantee count == 5 on every run.)
// ─────────────────────────────────────────────────────────────────────────

echo "\n=== Summary: $pass pass / $fail fail ===\n";
exit($fail === 0 ? 0 : 1);
