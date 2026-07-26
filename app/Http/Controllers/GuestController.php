<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestRequest;
use App\Http\Resources\GuestResource;
use App\Models\Guest;
use App\Support\RoleHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guest CRUD — Phase 04.
 *
 * Sits on top of:
 *   - `GuestRequest::prepareForValidation()` (column-level write stripping)
 *   - `GuestPolicy` (row-level authorization)
 *   - `GuestResource` (output masking for non-admin)
 *   - `RoleHelper::GROUP_GUEST_OWNER` (column-access matrix)
 *
 * Index scoping (per Implementation/Phase_04_Guest_Records_Core.md § 3):
 *   - Administrator: sees all guests.
 *   - Other roles: see only their assigned (follow_officer_id = user.id) OR
 *     their cell's (nearest_impact_cell_id = user.impact_cell_id) guests.
 *
 * Cross-cutting rule (per Implementation/Phase_04 § 2.2):
 *   - If `contacted_status` is being set to anything other than
 *     `AvailableForVisit`, server-side null out `visitation_status` and
 *     `feedback` (even if the client forgot). Applied in `store()` and
 *     `update()` after validation.
 *
 * Per Phase 04 spec, the controller does NOT yet emit audit log entries —
 * that's deferred to Phase 11 (Reports + Audit). The `activity()` helper
 * line is commented so the call site is reserved.
 */
class GuestController extends Controller
{
    /** The only `contacted_status` value that keeps `visitation_status` + `feedback` populated. */
    private const CONTACTED_STATUS_PRESERVES_VISITATION = 'AvailableForVisit';

    /**
     * GET /guests — paginated list scoped by role.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user?->activeRole();

        $query = Guest::query()->orderByDesc('created_at');

        // Per Phase 04 § 3 + Implementation/03 assignment rules:
        // non-admin users only see their own assigned / cell-scoped guests.
        if ($role !== 'Administrator' && $user !== null) {
            $query->where(function ($q) use ($user) {
                $q->where('follow_officer_id', $user->id);
                if ($user->impact_cell_id !== null) {
                    $q->orWhere('nearest_impact_cell_id', $user->impact_cell_id);
                }
            });
        }

        $guests = $query->paginate(20);

        return Inertia::render('Guests/Index', [
            'guests'    => GuestResource::collection($guests),
            'canCreate' => $user !== null && (
                $role === 'Administrator'
                || in_array($role, ['FollowUpOfficer', 'Follow_UP_Admin'], true)
            ),
            'activeRole' => $role,
            'groups'     => [
                'ownedByGroup' => RoleHelper::allGroupOwnedFields(),
                'groupOf'      => $role !== null ? RoleHelper::groupOf($role) : null,
            ],
        ]);
    }

    /** GET /guests/{id} */
    public function show(Request $request, string $id): Response
    {
        $guest = Guest::with(['nearestImpactCell', 'followOfficer'])->findOrFail($id);
        $this->authorize('view', $guest);

        return Inertia::render('Guests/Show', [
            'guest' => GuestResource::make($guest)->resolve($request),
        ]);
    }

    /**
     * POST /guests — Administrator + FollowUpOfficer (self-assigns).
     */
    public function store(GuestRequest $request): RedirectResponse
    {
        $this->authorize('create', Guest::class);

        $user = $request->user();
        $data = $this->applyCrossCuttingRules($request->validated());

        // Non-admin: force self-assignment (Matrix "Initial assignment at
        // create: Admin only" — FollowUpOfficer "Self-assign allowed").
        // The Form Request's `prepareForValidation()` will already have
        // stripped any client-provided `follow_officer_id` (it's not in
        // any group's matrix), so the controller is the only writer.
        $role = $user?->activeRole();
        if ($role !== 'Administrator' && $user !== null) {
            $data['follow_officer_id'] = $user->id;
        }

        $guest = Guest::create($data);

        // TODO(Phase 11): activity()->causedBy($user)->performedOn($guest)->log('GUEST_CREATED');

        return redirect()
            ->route('guests.show', $guest->id)
            ->with('success', "Guest {$guest->guest_name} created.");
    }

    /**
     * PUT /guests/{id} — Administrator + scoped per policy.
     */
    public function update(GuestRequest $request, string $id): RedirectResponse
    {
        $guest = Guest::findOrFail($id);
        $this->authorize('update', $guest);

        $data = $this->applyCrossCuttingRules($request->validated());

        $guest->update($data);

        // TODO(Phase 11): activity()->causedBy($request->user())->performedOn($guest)->withProperties(['before' => $before, 'after' => $after])->log('GUEST_UPDATED');

        return redirect()
            ->route('guests.show', $guest->id)
            ->with('success', "Guest {$guest->guest_name} updated.");
    }

    /** DELETE /guests/{id} — Administrator only. */
    public function destroy(string $id): RedirectResponse
    {
        $guest = Guest::findOrFail($id);
        $this->authorize('delete', $guest);

        $name = $guest->guest_name;
        $guest->delete();   // soft delete via the SoftDeletes trait

        return redirect()
            ->route('guests.index')
            ->with('success', "Guest {$name} deleted.");
    }

    /**
     * Cross-cutting rule (per Implementation/Phase_04 § 2.2):
     * If `contacted_status` is being set to anything other than
     * `AvailableForVisit`, null out `visitation_status` and `feedback`.
     *
     * Applied AFTER validation so the user's raw input is validated first
     * (e.g. `contacted_status` must be a valid string); then the rule
     * wipes visitation fields if the rule applies.
     *
     * @param  array<string, mixed> $data  already-validated data
     * @return array<string, mixed>
     */
    private function applyCrossCuttingRules(array $data): array
    {
        if (
            array_key_exists('contacted_status', $data)
            && $data['contacted_status'] !== self::CONTACTED_STATUS_PRESERVES_VISITATION
        ) {
            $data['visitation_status'] = null;
            $data['feedback']          = null;
        }

        return $data;
    }
}
