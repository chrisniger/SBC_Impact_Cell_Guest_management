<?php

namespace App\Http\Requests;

use App\Support\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Phase 13 — public signup FormRequest at /register.
 *
 * Validates the wire shape from the Auth/Register page (the React page
 * mirrors this surface). Replaces the in-controller `$request->validate()`
 * call that lived in `Auth\RegisteredUserController::store` since Phase 0;
 * extracting it into a FormRequest gets us:
 *   - prepareForValidation that defaults `active_role` (mirrors AdminUserRequest)
 *   - shared messages
 *   - easier test surface
 *
 * Acceptance surface
 * ------------------
 *   - name                  required string
 *   - email                 required + unique (excludes trashed users so
 *                           a re-signup with the same email still passes
 *                           after soft-delete)
 *   - password              required + confirmed + Password::defaults()
 *   - roles[]               non-empty array, each ∈ RoleHelper::SIGNUP_VISIBLE_ROLES
 *                           (Administrator / Supervisor / cross-cell-admin roles
 *                           are NOT pickable on the public form — admin must
 *                           promote those via Admin/Users/Edit)
 *   - active_role           must be in chosen roles[]
 *   - impact_cell_id        nullable UUID; required if Impact_Leaders is in roles[]
 *                           (Rule::requiredIf with closure)
 *   - leader_name /
 *     leader_phone /
 *     assistant_name /
 *     assistant_phone /
 *     welfare_officer_name /
 *     welfare_officer_phone — nullable free-text; admin can edit later
 *
 * Side effects (NOT validated here, fired by the controller)
 * -------------------------------------------------------------
 *   - Email verification + Auth::login happen in the controller after rules pass.
 *   - The `Auth\RegisteredUserController::store` controller also updates
 *     ImpactCell::leader_* fields when Impact_Leaders chose a cell.
 */
class RegisterInertiaRequest extends FormRequest
{
    /**
     * 2026-08-03 — the 7 optional cell-setup fields. Shared by the
     * prepareForValidation() sentinel-string normalisation so the list of
     * fields that may arrive as the literal "undefined"/"null" lives in one
     * place (mirrors Auth/Register.tsx HIDDEN_CELL_FIELDS).
     */
    private const CELL_FIELDS = [
        'impact_cell_id',
        'leader_name',
        'leader_phone',
        'assistant_name',
        'assistant_phone',
        'welfare_officer_name',
        'welfare_officer_phone',
    ];

    /**
     * Public form — no auth required.
     * The Phase 13 product req: any visitor can self-register as
     * `Impact_Leaders` + the two FollowUpOfficer-tier roles. Everything
     * else (admin / supervisor / cross-cell-admin) stays admin-only.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mirrors `AdminUserRequest::prepareForValidation` so the active-role
     * select on the form always resolves to a valid value once the role
     * checkbox grid changes underneath it. Without this, unchecking the
     * previously-active role would surface `The active role must be one of
     * the assigned roles` on submit (real bug, observed in Phase 06e+3).
     */
    public function prepareForValidation(): void
    {
        $merged = $this->all();

        if (
            empty($merged['active_role'])
            || (is_array($merged['roles'] ?? null) && ! in_array($merged['active_role'], $merged['roles'], true))
        ) {
            $merged['active_role'] = $merged['roles'][0] ?? '';
        }

        // Defence-in-depth — frontend always sends an array but the wire
        // can contain a single string if the form is hand-rolled.
        if (isset($merged['roles']) && ! is_array($merged['roles'])) {
            $merged['roles'] = [$merged['roles']];
        }

        // 2026-08-03 — zonal-only signup hardening. React-controlled forms can
        // ship the LITERAL STRING "undefined" (or "null") for fields that were
        // absent from the DOM at submit time — the cell-setup panel is unmounted
        // unless Impact_Leaders is checked, so impact_cell_id / leader_* arrived
        // as "undefined" and failed the `uuid` rule with an invisible error
        // (the InputError renders inside the unmounted panel). Normalise these
        // sentinel strings back to null so a missing optional field is treated
        // as absent, never as garbage. See also Auth/Register.tsx toggleRole()
        // (function-form setData) + the toWire() submit sanitizer.
        foreach (self::CELL_FIELDS as $field) {
            if (isset($merged[$field])
                && ($merged[$field] === 'undefined' || $merged[$field] === 'null')) {
                $merged[$field] = null;
            }
        }

        $this->replace($merged);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'email'       => [
                'required',
                'string',
                'email',
                'lowercase',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password'    => ['required', 'confirmed', Password::defaults()],

            // Role picker — public-allowlist only.
            'roles'       => ['required', 'array', 'min:1'],
            'roles.*'     => ['required', 'string', Rule::in(RoleHelper::SIGNUP_VISIBLE_ROLES)],
            'active_role' => [
                'required',
                'string',
                Rule::in($this->input('roles', [])),
            ],

            // Phase 13 — assigned impact cell + the 6 free-text leadership-team
            // seed fields. The dropdown + the cell-detail panel are ALWAYS
            // rendered on the public form (per the product choice — leader
            // assignment happens here OR via Admin/Users/Edit). Validation
            // here only enforces leader-cell pairing; FollowUpOfficer / etc.
            // may leave all 7 fields blank and still pass.
            'impact_cell_id'         => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('impact_cells', 'id'),
                // Required if-and-only-if Impact_Leaders is in roles[].
                Rule::requiredIf(fn () => $this->requiresImpactLeader()),
                // Phase 18 — one-credential-per-cell invariant. Self-edit
                // ignore is irrelevant on the SIGNUP path (no $targetUser).
                new \App\Rules\ImpactCellHasNoLiveLeader(),
            ],
            // Phase 23 — Impact Cell Leaders MUST supply their own contact
            // data. The other 4 leadership-team fields (assistant_*,
            // welfare_*) stay optional — Admin can fill them later from
            // the cell's own page once the leader is in place.
            'leader_name'            => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $this->requiresImpactLeader())],
            'leader_phone'           => ['nullable', 'string', 'max:32',  Rule::requiredIf(fn () => $this->requiresImpactLeader())],
            'assistant_name'         => ['nullable', 'string', 'max:255'],
            'assistant_phone'        => ['nullable', 'string', 'max:32'],
            'welfare_officer_name'   => ['nullable', 'string', 'max:255'],
            'welfare_officer_phone'  => ['nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required'              => 'Pick at least one role.',
            'roles.*.in'                  => 'That role is not available for self-signup.',
            'impact_cell_id.required_if'  => 'Impact Cell Leaders must pick the cell they lead.',
            'leader_name.required_if'     => 'Leader name is required for an Impact Leaders signup.',
            'leader_phone.required_if'    => 'Leader phone is required for an Impact Leaders signup.',
            'impact_cell_id.exists'       => 'The selected Impact Cell does not exist.',
            'active_role.in'              => 'Active role must be one of the chosen roles.',
        ];
    }

    /**
     * Phase 23 — single source of truth for "this signup requires an
     * Impact_Leaders contact data" predicate. Called by the 3 rules
     * in this FormRequest (impact_cell_id + leader_name + leader_phone)
     * so renaming the role in 1 place reflects everywhere.
     */
    protected function requiresImpactLeader(): bool
    {
        return in_array('Impact_Leaders', (array) ($this->input('roles', [])), true);
    }
}
