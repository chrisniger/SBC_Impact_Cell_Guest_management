<?php

/**
 * Repro: validate the exact payload the Guests/Edit.tsx form submits for
 * an Administrator, using GuestRequest::rules() directly. For admin the
 * prepareForValidation() strip is a pass-through, so testing rules()
 * against the filtered payload is faithful.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Requests\GuestRequest;
use App\Http\Resources\GuestResource;
use App\Models\Guest;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

$guest = Guest::first();
$user = User::where('email', 'sbcadmin@impact.test')->first();
if (! $guest || ! $user) {
    echo "missing guest/admin\n";
    exit(1);
}

$role = $user->activeRole();
echo "guest: {$guest->guest_name} (id {$guest->id})\n";
echo "role: {$role}\n\n";

// 1. Resource payload (exactly what the FE receives into useForm data)
$resource = (new GuestResource($guest))->resolve(Request::create('/'));

// 2. Mirror GuestController::computeEditableKeysForRole
$allPossible = array_merge(
    RoleHelper::allGroupOwnedFields(),
    ['guest_name', 'date', 'event', 'event_other', 'source', 'follow_officer_id']
);
$editable = array_keys(
    RoleHelper::stripDisallowed($role, array_fill_keys($allPossible, true))
);

// 3. The FE whitelist filter
$allowed = array_intersect_key($resource, array_flip($editable));

// 4. Simulate an edit: change guest_name like the user does
$allowed['guest_name'] = 'QA Rename Test';

// 5. Run through the REAL GuestRequest rules
$rules = (new GuestRequest())->rules();
$validator = Validator::make($allowed, $rules);

echo "VALIDATION " . ($validator->fails() ? 'FAILED' : 'PASSED') . "\n";
if ($validator->fails()) {
    foreach ($validator->errors()->toArray() as $field => $msgs) {
        echo "  - {$field}: " . implode('; ', $msgs) . "\n";
    }
} else {
    echo "  all rules pass — guest_name would save.\n";
}
