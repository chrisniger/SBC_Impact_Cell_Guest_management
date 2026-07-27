<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestRequest;
use App\Http\Resources\GuestResource;
use App\Models\Guest;
use App\Models\NotificationSetting;
use App\Support\RoleHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        // Pass `editableFields` to Show.tsx so the Edit link can be gated
        // — users without update perms shouldn't see a button that 403s.
        $role = $request->user()?->activeRole();

        return Inertia::render('Guests/Show', [
            'guest'          => GuestResource::make($guest)->resolve($request),
            'editableFields' => $this->computeEditableKeysForRole($role),
            'activeRole'     => $role,
        ]);
    }

    /**
     * GET /guests/{id}/edit — Render the edit form (Phase 05 follow-up).
     *
     * Source-of-truth pattern: instead of hardcoding which fields each
     * role can write in React, we run EVERY conceivable Guest writable
     * field through `RoleHelper::stripDisallowed()` — the keys that
     * survive are exactly what THIS user can write. The frontend then
     * uses the same list to decide which inputs to render.
     *
     * This keeps the FE and BE in lockstep — if the matrix changes in
     * `RoleHelper`, the form auto-adapts with zero React changes.
     *
     * Authorization uses `update` (which implies `view`) — only callers
     * who could already save the row should reach the edit screen.
     */
    public function edit(Request $request, string $id): Response
    {
        $guest = Guest::findOrFail($id);
        $this->authorize('update', $guest);

        $user = $request->user();
        $role = $user?->activeRole();
        $editableKeys = $this->computeEditableKeysForRole($role);

        // Only load the Impact Cell dropdown if the user can edit it.
        // Saves an extra DB query for FollowUpOfficers.
        $impactCells = in_array('nearest_impact_cell_id', $editableKeys, true)
            ? \App\Models\ImpactCell::orderBy('name')->get(['id', 'name'])->toArray()
            : [];

        return Inertia::render('Guests/Edit', [
            'guest'          => GuestResource::make($guest)->resolve($request),
            'editableFields' => $editableKeys,
            'impactCells'    => $impactCells,
            'activeRole'     => $role,
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

        // Phase 09b — fire GUEST_ASSIGNED_TO_IMPACT_LEADER notification on initial assignment.
        // Mirrors Phase 09's notifyReportSubmitted per-rule + try/catch + log shape.
        $this->sendGuestAssignedNotification($guest);

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

        // Capture the prior impact-cell assignment BEFORE write so we can detect post-write change.
        // Phase 09b: GUEST_ASSIGNED notification fires only when assignment actually changed (not on every update).
        $beforeCellId = $guest->nearest_impact_cell_id;

        $data = $this->applyCrossCuttingRules($request->validated());

        $guest->update($data);

        // Phase 09b — fire GUEST_ASSIGNED_TO_IMPACT_LEADER helper ONLY when nearest_impact_cell_id
        // changed (assigned to a new cell, or unassigned-to-assigned, or vice versa).
        if (($beforeCellId ?? '') !== ($guest->nearest_impact_cell_id ?? '')) {
            $this->sendGuestAssignedNotification($guest);
        }

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
     * PATCH /guests/{id}/follow-up-status — Phase 06 inline update.
     *
     * Lightweight endpoint used by the Team Dashboard's inline status
     * dropdown. Only updates follow_up_status — no other fields accepted.
     * The regular PUT /guests/{id} route (GuestController::update) is
     * used for full-edit form submissions.
     */
    public function updateFollowUpStatus(Request $request, string $id): JsonResponse
    {
        $guest = Guest::findOrFail($id);
        $this->authorize('update', $guest);

        $validated = $request->validate([
            'follow_up_status' => ['nullable', 'string', 'max:64'],
        ]);

        $guest->update(['follow_up_status' => $validated['follow_up_status'] ?? null]);

        return response()->json([
            'success'          => true,
            'follow_up_status' => $guest->fresh()->follow_up_status,
        ]);
    }

    /**
     * PATCH /guests/{id}/impact-status — Phase 07 inline update.
     *
     * Mirrors updateFollowUpStatus() but for impact_status (Impact Cell
     * group's editable column per RoleHelper::GROUP_GUEST_OWNER).
     * GuestPolicy::update() gates per-row — impactCell user can update
     * only when $guest->nearest_impact_cell_id === $user->impact_cell_id.
     *
     * **Column-level guard**: GuestPolicy does NOT gate by column group,
     * so without the explicit check below, a FollowUpOfficer assigned to
     * the guest could PATCH its impact_status through this endpoint
     * (impact_status is reserved for impactCell group per the matrix).
     * The dedicated endpoint keeps the Inertia-side pill component
     * (InlineImpactStatusPill) lightweight — no full-page reload.
     *
     * **Empty-string → null normalization**: PHP's `??` only collapses
     * `null`, not `''`. If the frontend Clear-status path sends an empty
     * string instead of null, the DB row gets `''` instead of NULL
     * (and downstream `Guest::whereNull('impact_status')` queries miss
     * it). The lint coerces `''` to null so the column stays a true
     * three-state value.
     */
    public function updateImpactStatus(Request $request, string $id): JsonResponse
    {
        // Column-level role gate FIRST (fail-fast + defense-in-depth: attacker
        // can't probe the policy class with unauthorized requests).
        //
        // Permission chain:
        //   1. Role gate  — must be Administrator OR a role whose
        //                   `groupOf($role) === 'impactCell'`
        //                   (see RoleHelper::GROUP_IMPACT_CELL for the
        //                   current member list).
        //   2. Row gate   — GuestPolicy::update (impactCell user can only
        //                   write guests whose nearest_impact_cell_id
        //                   matches their impact_cell_id).
        $role = $request->user()?->activeRole();
        $group = RoleHelper::groupOf($role);
        if ($role !== 'Administrator' && $group !== RoleHelper::GROUP_KEY_IMPACT_CELL) {
            abort(403, 'Only Impact Cell leaders (and Administrators) can edit impact_status.');
        }

        $guest = Guest::findOrFail($id);
        $this->authorize('update', $guest);

        $validated = $request->validate([
            'impact_status' => ['nullable', 'string', 'max:64'],
        ]);

        $value = $validated['impact_status'];
        if ($value === '') {
            $value = null;
        }

        $guest->update(['impact_status' => $value]);

        return response()->json([
            'success'       => true,
            'impact_status' => $guest->fresh()->impact_status,
        ]);
    }

    /**
     * Compute the universe of Guest columns this role may write.
     *
     * Single source of truth shared by `show()` (to gate the Edit link)
     * and `edit()` (to drive the Edit form's reactive inputs). Both
     * sites must derive the list the same way — if they drift, Show.tsx
     * could show a button that 403s, or Edit.tsx could render inputs
     * the role can't actually write.
     *
     * Algorithm: enumerate every conceivable writable field on Guest,
     * then run it through `RoleHelper::stripDisallowed($role, ...)`.
     * The keys that survive are exactly what `$role` may write.
     *
     * @return array<int, string>  snake_case column names
     */
    private function computeEditableKeysForRole(?string $role): array
    {
        $allPossible = array_merge(
            RoleHelper::allGroupOwnedFields(),
            ['guest_name', 'date', 'event', 'event_other', 'source', 'follow_officer_id']
        );

        return array_keys(
            RoleHelper::stripDisallowed($role, array_fill_keys($allPossible, true))
        );
    }

    /**
     * Phase 09b — fire GUEST_ASSIGNED notification to all enabled rules.
     * Mirrors Phase 09's notifyReportSubmitted try/catch + per-rule recipient pattern.
     *
     * For each `NotificationSetting.action = 'GUEST_ASSIGNED'` and `enabled = true`:
     *   - Build email body reporting the assignment (incl. cell id, guest name + phone).
     *   - Send via `Mail::raw()` wrapped in try/catch (per-recipient isolation — a failure
     *     on one recipient never aborts the loop).
     *   - On failure, `Log::warning()` with the recipient's address + the exception message.
     *
     * Skips silently with `Log::info()` when no rules are configured.
     */
    private function sendGuestAssignedNotification(Guest $guest): void
    {
        $rules = NotificationSetting::where('action', 'GUEST_ASSIGNED')
            ->where('enabled', true)
            ->get();

        if ($rules->isEmpty()) {
            Log::info('GUEST_ASSIGNED notification skipped: no rules configured.');
            return;
        }

        $subject = "Guest Assigned: {$guest->guest_name}";
        $body    = "Guest {$guest->guest_name} (phone: {$guest->phone}) has been assigned to impact cell " . ($guest->nearest_impact_cell_id ?? '(unassigned)') . '.';

        foreach ($rules as $rule) {
            try {
                Mail::raw($body, function ($message) use ($rule, $subject) {
                    $message->to($rule->recipient_email)->subject($subject);
                });
            } catch (\Exception $e) {
                Log::warning("Failed to send GUEST_ASSIGNED email to {$rule->recipient_email}: " . $e->getMessage());
            }
        }
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
