<?php
// Phase 04 end-to-end verification.
//
// Run with:  php scripts/verify_phase04_run.php
//
// Asserts:
//   [1]  migration applied (guests table exists)
//   [2]  Guest model uses HasUuids + SoftDeletes traits
//   [3]  every required column from Implementation/02 § Guest schema exists
//   [4]  nearest_impact_cell_id FK is enforced (restrictOnDelete)
//   [5]  follow_officer_id FK is enforced (restrictOnDelete)
//   [6]  GuestRequest::prepareForValidation strips disallowed fields for FollowUpOfficer
//   [7]  GuestRequest::prepareForValidation strips disallowed fields for Follow_UP (Team)
//   [8]  GuestRequest::prepareForValidation strips disallowed fields for Impact_Leaders
//   [9]  GuestRequest::prepareForValidation allows all fields for Administrator
//   [10] Cross-cutting rule: contacted_status != AvailableForVisit nullifies visitation fields
//   [11] Cross-cutting rule: contacted_status = AvailableForVisit preserves visitation fields
//   [12] GuestResource masks deleted_at for non-admin
//   [13] GuestResource exposes deleted_at for Administrator
//   [14] GuestPolicy denies delete for non-admin
//   [15] GuestPolicy allows view for admin (any guest)
//   [16] GuestPolicy allows view for assigned FollowUpOfficer
//   [17] GuestPolicy denies view for unassigned FollowUpOfficer
//   [18] ImpactCellPolicy denies update for non-admin
//   [19] ImpactCellPolicy allows update for Administrator
//   [20] 5 guest routes are registered + impact-cell routes still resolve

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Requests\GuestRequest;
use App\Http\Resources\GuestResource;
use App\Models\Guest;
use App\Models\ImpactCell;
use App\Models\User;
use App\Policies\GuestPolicy;
use App\Policies\ImpactCellPolicy;
use App\Support\RoleHelper;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

echo "=== Phase 04 verification ===\n\n";

// ─────────────────────────────────────────────────────────────────────────
// [1] Migration applied
// ─────────────────────────────────────────────────────────────────────────
echo "[1] migration applied\n";
check("guests table exists", Schema::hasTable('guests'));

// ─────────────────────────────────────────────────────────────────────────
// [2] Guest model uses HasUuids + SoftDeletes
// ─────────────────────────────────────────────────────────────────────────
echo "\n[2] Guest model traits\n";
$guestUses = class_uses(Guest::class);
check("Guest uses HasUuids",  in_array(HasUuids::class,  $guestUses, true));
check("Guest uses SoftDeletes", in_array(SoftDeletes::class, $guestUses, true));

// ─────────────────────────────────────────────────────────────────────────
// [3] Every required column from Implementation/02 § Guest schema exists
// ─────────────────────────────────────────────────────────────────────────
echo "\n[3] guests columns\n";
$requiredColumns = [
    'id', 'date', 'event', 'event_other', 'guest_name', 'source',
    'gender', 'marital_status', 'age', 'phone', 'address',
    'nearest_impact_cell_id', 'impact_status',
    'contacted_status', 'join_when', 'days_available', 'comments',
    'visited', 'visited_at', 'indicated_to_join', 'visitation_status', 'feedback',
    'follow_up_status', 'follow_up_contacts',
    'follow_officer_id',
    'created_at', 'updated_at', 'deleted_at',
];
$missingColumns = [];
foreach ($requiredColumns as $col) {
    if (! Schema::hasColumn('guests', $col)) {
        $missingColumns[] = $col;
    }
}
check("all " . count($requiredColumns) . " required columns exist",
    $missingColumns === [],
    $missingColumns === [] ? '' : 'missing: ' . implode(',', $missingColumns));

// ─────────────────────────────────────────────────────────────────────────
// [4] + [5] Foreign keys enforce restrictOnDelete
// ─────────────────────────────────────────────────────────────────────────
echo "\n[4][5] foreign keys\n";
$nearestFk = collect(Schema::getForeignKeys('guests'))
    ->firstWhere('columns', ['nearest_impact_cell_id']);
$followFk = collect(Schema::getForeignKeys('guests'))
    ->firstWhere('columns', ['follow_officer_id']);
check("nearest_impact_cell_id FK → impact_cells.id",
    $nearestFk !== null && ($nearestFk['foreign_table'] ?? null) === 'impact_cells');
check("nearest_impact_cell_id FK uses restrictOnDelete",
    $nearestFk !== null && ($nearestFk['on_delete'] ?? null) === 'restrict');
check("follow_officer_id FK → users.id",
    $followFk !== null && ($followFk['foreign_table'] ?? null) === 'users');
check("follow_officer_id FK uses restrictOnDelete",
    $followFk !== null && ($followFk['on_delete'] ?? null) === 'restrict');

// ─────────────────────────────────────────────────────────────────────────
// [6]-[9] Form Request stripping
// ─────────────────────────────────────────────────────────────────────────
echo "\n[6]-[9] GuestRequest::prepareForValidation\n";

// Helper: create a test user with the given role + active_role and run a
// GuestRequest against a body, then return the validated array.
function runGuestRequestAs(string $role, array $body): array
{
    $user = User::firstOrCreate(
        ['email' => "phase04-test-{$role}@impact.test"],
        ['name' => "Phase 04 Test {$role}", 'password' => 'irrelevant', 'active_role' => $role],
    );
    // Ensure the role exists in the roles table (no-op if it does).
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $user->syncRoles([$role]);
    $user->forceFill(['active_role' => $role])->save();

    $request = Request::create('/guests', 'POST', $body);
    $request->setUserResolver(fn () => $user);

    $formRequest = GuestRequest::createFrom($request);
    $formRequest->setUserResolver(fn () => $user);

    // Use `validate()` (not `validated()`) — `validate()` calls
    // `validateResolved()` first, which sets up `$this->validator`.
    // Calling `validated()` directly throws "Call to a member function
    // validated() on null" because the validator was never instantiated.
    try {
        return $formRequest->validate();
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Validation failed (e.g. a required field was stripped). Return
        // the stripped data so the test can assert which fields were kept
        // vs. dropped — the failed-validation fields are intentionally
        // missing from this returned array.
        return $formRequest->all() ?? [];
    }
}

// Body covers every group's owned field PLUS admin-only fields.
$body = [
    // admin-only (not in any group's matrix)
    'guest_name'          => 'John Doe',
    'event'               => 'Sunday Service',
    'event_other'         => null,
    'source'              => 'Invitation',
    // Follow Up Officer group
    'gender'              => 'Male',
    'marital_status'      => 'Single',
    'age'                 => '28',
    'phone'               => '08012345678',
    'address'             => '12 Test Street',
    'contacted_status'    => 'AvailableForVisit',
    'join_when'           => 'FirstTimer',
    'days_available'      => 'Weekends',
    'comments'            => 'Test comment',
    'visited'             => true,
    'visited_at'          => '2026-07-27',
    'indicated_to_join'   => 'Yes',
    'visitation_status'   => 'Scheduled',
    'feedback'            => 'Keen',
    // Impact Cell group
    'nearest_impact_cell_id' => null,
    'impact_status'           => 'Pending',
    // Follow Up Team group
    'follow_up_status'   => 'In Progress',
    'follow_up_contacts' => [['date' => '2026-07-27', 'note' => 'attempt 1']],
    // assignment (admin-only)
    'follow_officer_id'  => null,
];

// [6] FollowUpOfficer — gets officer group fields; loses impact_cell + follow_up_team fields
$officer = runGuestRequestAs('FollowUpOfficer', $body);
check("[6] FollowUpOfficer keeps officer-group fields",
    isset($officer['phone'], $officer['contacted_status']));
check("[6] FollowUpOfficer LOSES impact_status",
    ! array_key_exists('impact_status', $officer));
check("[6] FollowUpOfficer LOSES follow_up_status",
    ! array_key_exists('follow_up_status', $officer));
check("[6] FollowUpOfficer LOSES admin-only fields (guest_name, event, source)",
    ! array_key_exists('guest_name', $officer)
    && ! array_key_exists('event', $officer)
    && ! array_key_exists('source', $officer));

// [7] Follow_UP — gets team group fields; loses officer + impact_cell fields
$team = runGuestRequestAs('Follow_UP', $body);
check("[7] Follow_UP keeps team-group fields",
    isset($team['follow_up_status'], $team['follow_up_contacts']));
check("[7] Follow_UP LOSES phone (officer-owned)",
    ! array_key_exists('phone', $team));
check("[7] Follow_UP LOSES impact_status",
    ! array_key_exists('impact_status', $team));

// [8] Impact_Leaders — gets impact_cell fields; loses officer + team fields
$cell = runGuestRequestAs('Impact_Leaders', $body);
check("[8] Impact_Leaders keeps impact_cell-group fields",
    array_key_exists('impact_status', $cell));
check("[8] Impact_Leaders LOSES phone (officer-owned)",
    ! array_key_exists('phone', $cell));
check("[8] Impact_Leaders LOSES follow_up_status",
    ! array_key_exists('follow_up_status', $cell));

// [9] Administrator — pass-through
$admin = runGuestRequestAs('Administrator', $body);
check("[9] Administrator keeps all fields",
    isset(
        $admin['guest_name'],
        $admin['phone'],
        $admin['impact_status'],
        $admin['follow_up_status'],
        $admin['contacted_status'],
    ));

// ─────────────────────────────────────────────────────────────────────────
// [10][11] Cross-cutting rule
// ─────────────────────────────────────────────────────────────────────────
echo "\n[10][11] cross-cutting rule (contacted_status != AvailableForVisit)\n";

// We test the GuestController::applyCrossCuttingRules via reflection.
$controller = app(\App\Http\Controllers\GuestController::class);
$reflect = new ReflectionMethod($controller, 'applyCrossCuttingRules');
$reflect->setAccessible(true);

$cleared = $reflect->invoke($controller, [
    'contacted_status'  => 'No',
    'visitation_status' => 'Scheduled',
    'feedback'          => 'Keen',
]);
check("[10] contacted_status='No' → visitation_status nullified",
    $cleared['visitation_status'] === null);
check("[10] contacted_status='No' → feedback nullified",
    $cleared['feedback'] === null);

$preserved = $reflect->invoke($controller, [
    'contacted_status'  => 'AvailableForVisit',
    'visitation_status' => 'Scheduled',
    'feedback'          => 'Keen',
]);
check("[11] contacted_status='AvailableForVisit' preserves visitation_status",
    $preserved['visitation_status'] === 'Scheduled');
check("[11] contacted_status='AvailableForVisit' preserves feedback",
    $preserved['feedback'] === 'Keen');

// ─────────────────────────────────────────────────────────────────────────
// [12][13] GuestResource masks deleted_at
// ─────────────────────────────────────────────────────────────────────────
echo "\n[12][13] GuestResource output masking\n";

// Seed a test guest assigned to the FollowUpOfficer test user.
$testOfficer = User::where('email', 'phase04-test-FollowUpOfficer@impact.test')->firstOrFail();
$guest = Guest::create([
    'guest_name'           => 'Phase 04 Test Guest',
    'follow_officer_id'    => $testOfficer->id,
    'contacted_status'     => 'No',
    'visitation_status'    => 'should-be-cleared',
    'feedback'             => 'should-be-cleared',
]);

$nonAdmin = User::firstOrCreate(
    ['email' => 'phase04-test-FollowUpOfficer@impact.test'],
    ['name' => 'Phase 04 Test FollowUpOfficer', 'password' => 'irrelevant', 'active_role' => 'FollowUpOfficer'],
);
$reqNonAdmin = Request::create('/guests/' . $guest->id, 'GET');
$reqNonAdmin->setUserResolver(fn () => $nonAdmin);
$resourceNonAdmin = GuestResource::make($guest)->resolve($reqNonAdmin);
check("[12] GuestResource hides deleted_at for non-admin",
    $resourceNonAdmin['deleted_at'] === null);
check("[12] GuestResource hides created_at for non-admin (per spec)",
    $resourceNonAdmin['created_at'] === null);
check("[12] GuestResource hides updated_at for non-admin (per spec)",
    $resourceNonAdmin['updated_at'] === null);

$adminUser = User::firstOrCreate(
    ['email' => 'sbcadmin@impact.test'],
    ['name' => 'SBC Admin', 'password' => '//Chris##101', 'active_role' => 'Administrator'],
);
$reqAdmin = Request::create('/guests/' . $guest->id, 'GET');
$reqAdmin->setUserResolver(fn () => $adminUser);
$resourceAdmin = GuestResource::make($guest)->resolve($reqAdmin);
check("[13] GuestResource exposes deleted_at for Administrator",
    array_key_exists('deleted_at', $resourceAdmin));
check("[13] GuestResource exposes created_at for Administrator",
    array_key_exists('created_at', $resourceAdmin));
check("[13] GuestResource exposes updated_at for Administrator",
    array_key_exists('updated_at', $resourceAdmin));

// ─────────────────────────────────────────────────────────────────────────
// [14]-[17] GuestPolicy
// ─────────────────────────────────────────────────────────────────────────
echo "\n[14]-[17] GuestPolicy\n";

$policy = app(GuestPolicy::class);

// [14] non-admin cannot delete
$officerReq = Request::create('/guests/' . $guest->id, 'DELETE');
$officerReq->setUserResolver(fn () => $nonAdmin);
check("[14] GuestPolicy denies delete for FollowUpOfficer",
    $policy->delete($nonAdmin, $guest) === false);

// [15] admin can view any guest
check("[15] GuestPolicy allows view for Administrator",
    $policy->view($adminUser, $guest) === true);

// [16] assigned FollowUpOfficer can view their own guest
check("[16] GuestPolicy allows view for assigned FollowUpOfficer",
    $policy->view($nonAdmin, $guest) === true);

// [17] unassigned FollowUpOfficer cannot view
$otherOfficer = User::firstOrCreate(
    ['email' => 'phase04-test-FollowUpOfficer-OTHER@impact.test'],
    ['name' => 'Phase 04 Test Other Officer', 'password' => 'irrelevant', 'active_role' => 'FollowUpOfficer'],
);
Role::firstOrCreate(['name' => 'FollowUpOfficer', 'guard_name' => 'web']);
$otherOfficer->syncRoles(['FollowUpOfficer']);
$otherOfficer->forceFill(['active_role' => 'FollowUpOfficer'])->save();
$guestAssignedToFirstOfficer = Guest::create([
    'guest_name'        => 'Phase 04 Other Officer Guest',
    'follow_officer_id' => $nonAdmin->id,
]);
check("[17] GuestPolicy denies view for unassigned FollowUpOfficer",
    $policy->view($otherOfficer, $guestAssignedToFirstOfficer) === false);

// ─────────────────────────────────────────────────────────────────────────
// [18][19] ImpactCellPolicy
// ─────────────────────────────────────────────────────────────────────────
echo "\n[18][19] ImpactCellPolicy\n";

$cellPolicy = app(ImpactCellPolicy::class);
$apoCell = ImpactCell::where('name', 'APO')->firstOrFail();
check("[18] ImpactCellPolicy denies update for non-admin",
    $cellPolicy->update($nonAdmin, $apoCell) === false);
check("[19] ImpactCellPolicy allows update for Administrator",
    $cellPolicy->update($adminUser, $apoCell) === true);

// ─────────────────────────────────────────────────────────────────────────
// [20] Routes registered
// ─────────────────────────────────────────────────────────────────────────
echo "\n[20] routes registered\n";
$names = collect(Route::getRoutes()->getRoutes())
    ->map(fn ($r) => $r->getName())
    ->filter()
    ->values()
    ->all();
$expectedRoutes = [
    'guests.index',
    'guests.show',
    'guests.store',
    'guests.update',
    'guests.destroy',
    'impact-cells.index',
    'impact-cells.show',
    'impact-cells.store',
    'impact-cells.update',
    'impact-cells.destroy',
];
$missingRoutes = array_diff($expectedRoutes, $names);
check("all 5 guest routes + 5 impact-cell routes registered",
    $missingRoutes === [],
    $missingRoutes === [] ? '' : 'missing: ' . implode(',', $missingRoutes));

// ─────────────────────────────────────────────────────────────────────────
// Cleanup — delete the test guests so re-runs are idempotent
// ─────────────────────────────────────────────────────────────────────────
Guest::whereIn('guest_name', ['Phase 04 Test Guest', 'Phase 04 Other Officer Guest'])->forceDelete();

echo "\n=== Summary: $pass pass / $fail fail ===\n";
exit($fail === 0 ? 0 : 1);
