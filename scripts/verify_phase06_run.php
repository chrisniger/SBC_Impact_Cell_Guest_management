<?php
/**
 * Phase 06 end-to-end verification.
 *
 * Run with:  php scripts/verify_phase06_run.php
 *
 * 14 sub-assertions covering:
 *   [1]-[4] Team test users + role assignment
 *   [5]-[6] Team fixture guests
 *   [7]-[10] Policy: Follow_UP can update, Follow_UP_View_Only cannot
 *   [11]-[12] RoleHelper group membership
 *   [13]-[14] DashboardController team variant renders
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

echo "=== Phase 06 verification ===\n\n";

// ─────────────────────────────────────────────────────────────────────────
// [1]-[4] Team test users + role assignment + active_role
// ─────────────────────────────────────────────────────────────────────────
echo "[1]-[4] Team test users\n";

$team1 = User::firstOrCreate(
    ['email' => 'team1@impact.test'],
    ['name' => 'Phase 06 Team Member', 'password' => '//Team##101', 'active_role' => 'Follow_UP'],
);
$team1->syncRoles(['Follow_UP']);
$team1->forceFill(['active_role' => 'Follow_UP'])->save();
$team1->refresh();

check('[1] team1@impact.test exists', $team1->exists);
check('[2] team1 has Follow_UP role + active_role',
    $team1->hasRole('Follow_UP') && $team1->active_role === 'Follow_UP',
    'activeRole() = ' . ($team1->activeRole() ?? 'null'));

$viewOnly = User::firstOrCreate(
    ['email' => 'teamViewOnly@impact.test'],
    ['name' => 'Phase 06 View Only', 'password' => '//ViewOnly##101', 'active_role' => 'Follow_UP_View_Only'],
);
$viewOnly->syncRoles(['Follow_UP_View_Only']);
$viewOnly->forceFill(['active_role' => 'Follow_UP_View_Only'])->save();
$viewOnly->refresh();

check('[3] teamViewOnly@impact.test exists', $viewOnly->exists);
check('[4] viewOnly has Follow_UP_View_Only role + active_role',
    $viewOnly->hasRole('Follow_UP_View_Only') && $viewOnly->active_role === 'Follow_UP_View_Only',
    'activeRole() = ' . ($viewOnly->activeRole() ?? 'null'));

// ─────────────────────────────────────────────────────────────────────────
// Verifier-only clean slate + re-seed
// ─────────────────────────────────────────────────────────────────────────
echo "\n[!] verifier-only: wipe Team Fixture guests, then re-seed\n";
Guest::where('guest_name', 'like', 'Team Fixture #%')->forceDelete();
(Illuminate\Support\Facades\Artisan::call('db:seed', [
    '--class' => 'Database\\Seeders\\FollowUpTeamSeeder',
    '--force' => true,
]));
echo "  (verifier seeded 5 marker guests via FollowUpTeamSeeder)\n\n";

// ─────────────────────────────────────────────────────────────────────────
// [5]-[6] Team fixture guests exist with expected follow_up_status values
// ─────────────────────────────────────────────────────────────────────────
echo "[5]-[6] Team fixture guests\n";

$fixtures = Guest::where('guest_name', 'like', 'Team Fixture #%')->whereNull('deleted_at')->get();
check('[5] exactly 5 team fixture guests exist',
    $fixtures->count() === 5,
    'count = ' . $fixtures->count());

$statusesPresent = $fixtures->pluck('follow_up_status')->map(fn ($v) => $v ?? 'NULL')->unique()->values()->all();
$expectedStatuses = ['NULL', 'NOT CONTACTED', 'CONTACTED', 'WRONG NUMBER', 'NOT REACHABLE'];
$expectedSet = collect($expectedStatuses);
$presentSet = collect($statusesPresent);
check('[6] fixtures span all 5 follow_up_status permutations',
    $expectedSet->diff($presentSet)->isEmpty() && $presentSet->diff($expectedSet)->isEmpty(),
    'got: [' . implode(', ', $statusesPresent) . '] expected: [' . implode(', ', $expectedStatuses) . ']');

// ─────────────────────────────────────────────────────────────────────────
// [7]-[10] Policy assertions
// ─────────────────────────────────────────────────────────────────────────
echo "\n[7]-[10] Policy: Follow_UP can update, Follow_UP_View_Only cannot\n";

// Create a simple policy test guest
$testGuest = Guest::where('guest_name', 'Team Fixture #1')->first();
check('[7] fixture guest #1 exists', $testGuest !== null && $testGuest->exists);

$policy = app(\App\Policies\GuestPolicy::class);

$canUpdateTeam1  = $policy->update($team1, $testGuest);
$canUpdateViewOnly = $policy->update($viewOnly, $testGuest);

check('[8] Follow_UP can update guests (assigned to any officer via team view)',
    $canUpdateTeam1 === true,
    'Follow_UP update = ' . ($canUpdateTeam1 ? 'true' : 'false'));

check('[9] Follow_UP_View_Only cannot update guests',
    $canUpdateViewOnly === false,
    'Follow_UP_View_Only update = ' . ($canUpdateViewOnly ? 'true' : 'false'));

$viewAnyTeam1 = $policy->view($team1, $testGuest);
check('[10] Follow_UP can view any guest (team queue view)',
    $viewAnyTeam1 === true,
    'Follow_UP view = ' . ($viewAnyTeam1 ? 'true' : 'false'));

// ─────────────────────────────────────────────────────────────────────────
// [11]-[12] RoleHelper group membership
// ─────────────────────────────────────────────────────────────────────────
echo "\n[11]-[12] RoleHelper group membership\n";

check('[11] Follow_UP is in followUpTeam group',
    RoleHelper::groupOf('Follow_UP') === 'followUpTeam',
    'groupOf(Follow_UP) = ' . (RoleHelper::groupOf('Follow_UP') ?? 'null'));

check('[12] Follow_UP_View_Only is in followUpTeam group',
    RoleHelper::groupOf('Follow_UP_View_Only') === 'followUpTeam',
    'groupOf(Follow_UP_View_Only) = ' . (RoleHelper::groupOf('Follow_UP_View_Only') ?? 'null'));

// ─────────────────────────────────────────────────────────────────────────
// [13]-[14] DashboardController team variant
// ─────────────────────────────────────────────────────────────────────────
echo "\n[13]-[14] DashboardController team variant\n";

$controller = app(DashboardController::class);
$request = Request::create('/dashboard', 'GET');
$request->setUserResolver(fn () => $team1);

$response = $controller->index($request);

if (! $response instanceof Response) {
    echo "  FAIL  [13] controller did not return Inertia\\Response\n";
    $fail += 2;
} else {
    $reflect   = new ReflectionClass($response);
    $propsProp = $reflect->getProperty('props');
    $propsProp->setAccessible(true);
    $props = $propsProp->getValue($response);

    check('[13] variant = "team" for Follow_UP user',
        ($props['variant'] ?? null) === 'team',
        'variant = ' . ($props['variant'] ?? 'null'));

    check('[14] team KPIs are present (pendingContacts)',
        isset($props['kpis']['pendingContacts']),
        'pendingContacts = ' . ($props['kpis']['pendingContacts'] ?? 'undefined'));
}

// ─────────────────────────────────────────────────────────────────────────
echo "\n=== Summary: $pass pass / $fail fail ===\n";
exit($fail === 0 ? 0 : 1);
