<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Phase 18 — One-credential-per-Impact-Cell invariant.
 *
 * Passes iff no OTHER (live, Role-pinned-to-Impact_Leaders) User row
 * holds the same `impact_cell_id`. Used by both the public signup
 * FormRequest (RegisterInertiaRequest) and the admin FormRequest
 * (AdminUserRequest) so the rule fires identically in both flows. The
 * same predicate ALSO powers the 3 controller race-condition rechecks
 * (RegisteredUserController::store + Admin\\UserController::store +
 * Admin\\UserController::update) — every recheck site calls the named
 * `hasLiveLeader()` static so the predicate lives in exactly one place.
 *
 * Scope: Impact_Leaders ONLY.
 *   - Impact_Zonal_Coordinator uses a many-to-many pivot
 *     (`impact_cell_user`) and intentionally covers multiple cells,
 *     so this Rule does not apply there.
 *   - Cross-cell-supervisor roles (Impact_Cell_Admin, Impact_Cell_Report)
 *     bind via the pivot, not `users.impact_cell_id`, so they
 *     naturally skip this check (impact_cell_id is NULL for them).
 *
 * Soft-delete semantics:
 *   - The User model uses `Illuminate\\Database\\Eloquent\\SoftDeletes`,
 *     so the default query scope `whereNull('deleted_at')` is
 *     auto-applied and a soft-deleted leader does NOT occupy the slot.
 *     A new signup / admin create succeeds against a freed cell.
 *     There is NO need for an app-side clause sprinkling — and adding
 *     one in the recheck sites is REDUNDANT NOISE that frames the
 *     predicate as a row-count rather than a membership check (the
 *     exact framing Phase 19 corrected).
 *
 * Self-edit / admin override (ignoreUserId):
 *   - When the admin edits an existing Impact_Leaders user's profile,
 *     the rule is constructed with `new ImpactCellHasNoLiveLeader($targetId)`
 *     so the editing user themselves are excluded from the existence
 *     check (and the controller's race-condition recheck passes
 *     `$user->id` to `hasLiveLeader()` directly). This makes
 *     name/email/password edits (with the same cell binding) pass
 *     without tripping the invariant.
 *
 * Race-condition guard:
 *   - The FormRequest layer surfaces a clean 422 plus the friendly
 *     error message before the row is created, but it cannot prevent
 *     simultaneous POSTs from racing past each other. The controllers
 *     are expected to additionally wrap the create/update inside
 *     `DB::transaction()` and re-check THIS predicate against a
 *     `lockForUpdate()` row lock on the candidate cell. That recheck
 *     is belt-and-suspenders and is the only layer that defeats the
 *     race window. See `hasLiveLeader()` for the canonical predicate.
 *
 * Predicate shape — Phase 19 tightening
 * ──────────────────────────────────────
 * The user-facing invariant is one sentence:
 *     "ONE login Credentials Per Impact Cell."
 * Translated into a membership predicate:
 *     "Does this Impact Cell ALREADY HOLD a live User row whose Spatie
 *      role-set includes `Impact_Leaders`?"
 *
 * That is a MEMBERSHIP CHECK (returns one boolean), NOT a row count.
 * The previous inline form (`$conflicting = User::query()->...->exists()`)
 * had two readability hazards: (1) the variable `$conflicting` reads
 * as "rows that conflict" (row-count framing), and (2) the WHERE-chain
 * was duplicated 4 times across the codebase. Phase 19 collapsed it
 * into `hasLiveLeader(string $cellId, ?int $ignoreUserId = null): bool`
 * so call sites read in plain English:
 *
 *     ImpactCellHasNoLiveLeader::hasLiveLeader($cellId)
 *     ImpactCellHasNoLiveLeader::hasLiveLeader($cellId, $user->id)
 */
class ImpactCellHasNoLiveLeader implements ValidationRule
{
    /**
     * Phase 19 — single source of truth for the friendly 422 message
     * surfaced by this Rule AND by the 3 controller rechecks that
     * call `hasLiveLeader()`. Match exactly; Phase 18 test 1 asserts
     * the message verbatim via `assertSessionHasErrors([key => msg])`.
     */
    public const OCCUPIED_MESSAGE = 'This Impact Cell Already has a Login Credentials, reset the password or ask the Admin for login details.';

    public function __construct(protected mixed $ignoreUserId = null)
    {
    }

    /**
     * Validation-rule entry point (Laravel's FormRequest resolution).
     *
     * Returns silently on success; calls `$fail(...)` with the user-facing
     * message on conflict. Delegates the actual predicate check to
     * `hasLiveLeader()` so the membership check lives in ONE place —
     * every controller recheck site calls the same helper, not a copy
     * of the SQL.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // No cell binding submitted — nothing to check (Phase 13's
        // `required_if` covers the bound-leader case; this rule is the
        // round-trip when a cell WAS supplied).
        if (empty($value)) {
            return;
        }

        if (self::hasLiveLeader((string) $value, $this->ignoreUserId !== null ? (int) $this->ignoreUserId : null)) {
            $fail(self::OCCUPIED_MESSAGE);
        }
    }

    /**
     * The single-source-of-truth MEMBERSHIP predicate (Phase 19 tightening).
     *
     * Returns `true` iff there is at least one LIVE User row whose
     * `impact_cell_id` matches `$cellId` AND whose Spatie role-set
     * includes `Impact_Leaders`. Excludes `$ignoreUserId` if given,
     * so admin self-edits don't trip the invariant.
     *
     * Three facts make this a clean membership check, not a row count:
     *   1. The query terminal is `->exists()` (one boolean), NOT
     *      `->count()` (an int that the caller might threshold later).
     *   2. Soft-deleted users are auto-filtered by User's global
     *      SoftDeletes scope (`whereNull('deleted_at')`). The clause
     *      is NOT repeated in the predicate body — adding it would
     *      be redundant noise. (See class docblock "Soft-delete
     *      semantics" for the empirical reasoning.)
     *   3. Role membership is enforced via `whereHas('roles', ...)`
     *      against the SPATIE role-set, NOT against `users.active_role`
     *      (which is the user's *currently-displayed* role, mutable via
     *      `Auth\\RoleSwitchController`). A former Impact_Leaders user
     *      who has switched their display role still occupies the slot
     *      because the Spatie role membership is unchanged.
     *
     * @param  string  $cellId        The candidate Impact Cell id this signup/update wants to bind.
     * @param  int|null $ignoreUserId Optional user id to exclude from the membership check (admin self-edit).
     * @return bool                   True iff the slot is already occupied.
     */
    public static function hasLiveLeader(string $cellId, ?int $ignoreUserId = null): bool
    {
        return User::query()
            ->where('impact_cell_id', $cellId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Impact_Leaders'))
            ->when(
                $ignoreUserId !== null,
                fn ($q) => $q->where('id', '!=', $ignoreUserId)
            )
            ->exists();
    }
}
