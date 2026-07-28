<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Phase 06e+3 — FormRequest for `/admin/users` create + edit (PUT/PATCH).
 *
 * Validates the wire-shape from the Admin/Users Add modal AND the
 * Admin/Users/{user}/edit page. Single form-request class keeps the
 * role-name list + email-uniqueness + active-role-in-roles rule
 * canonical across both flows; conditionally relaxes `password` on
 * update (blank = keep current, filled = set a new one).
 *
 * Self-demote invariant
 * ---------------------
 * Re-added in Phase 06e+3 because the edit page now exposes a public
 * `update()` endpoint. The current actor cannot remove the Administrator
 * Spatie role from their own record — the helper is called from
 * `Admin\\UserController::update()` AFTER the standard rules pass so
 * the Inertia form receives the error via `errors.roles`.
 *
 * Validation surface
 * ------------------
 *   - name:          required string
 *   - email:         required, unique (excluding self + trashed rows so
 *                    a later re-creation with the same email doesn't trip)
 *   - roles[]:       non-empty array, each ∈ RoleHelper::ROLE_NAMES
 *   - active_role:   must be in the chosen roles array (a select bound
 *                    to `roles` cannot point at a role not held)
 *   - password:      required + confirmed on create; nullable + confirmed on update
 */
class AdminUserRequest extends FormRequest
{
    /** Administrator-only; Phase 06e+1 codifies users as a root privilege. */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Administrator') ?? false;
    }

    public function prepareForValidation(): void
    {
        $merged = $this->all();

        // Default active_role to roles[0] if missing OR no longer in
        // the roles array (e.g. the admin unchecked the previously-active
        // role in the grid). Both create and update benefit from this
        // so the rule below always has a value to validate.
        if (
            empty($merged['active_role'])
            || (is_array($merged['roles'] ?? null) && ! in_array($merged['active_role'], $merged['roles'], true))
        ) {
            $merged['active_role'] = $merged['roles'][0] ?? '';
        }

        // Defence-in-depth — frontend always sends an array.
        if (isset($merged['roles']) && ! is_array($merged['roles'])) {
            $merged['roles'] = [$merged['roles']];
        }

        $this->replace($merged);
    }

    public function rules(): array
    {
        // `{user}` route segment on PUT/PATCH; ID via route-model binding
        // — falls back to null on POST (no ignore → strict uniqueness).
        $targetId = $this->route('user')?->id ?? $this->input('user');

        $rules = [
            'name'        => ['required', 'string', 'max:255'],
            'email'       => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($targetId)->whereNull('deleted_at'),
            ],
            'roles'       => ['required', 'array', 'min:1'],
            'roles.*'     => ['required', 'string', Rule::in(RoleHelper::ROLE_NAMES)],
            'active_role' => [
                'required',
                'string',
                Rule::in($this->input('roles', [])),
            ],
        ];

        // Password is mandatory on create, optional on edit (blank = keep
        // the existing hash; non-blank must satisfy Password::defaults()
        // and the password_confirmation field).
        $rules['password'] = $this->isMethod('POST')
            ? ['required', 'confirmed', Password::defaults()]
            : ['nullable', 'confirmed', Password::defaults()];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'A user must have at least one role.',
            'active_role.in' => 'The active role must be one of the assigned roles.',
        ];
    }

    /**
     * Phase 06e+3 — self-protection: prevent the current actor from
     * removing the Administrator role from their own record via this
     * form request.
     *
     * Called from `Admin\\UserController::update()` AFTER the standard
     * rules pass (so regular rule errors still surface normally). A
     * ValidationException here bubbles up through Inertia's error map
     * with `errors.roles` containing the message — the form can
     * highlight the role checkbox inline.
     *
     * No-op for non-self-edit paths (returns silently) so the helper
     * is a one-liner call from any controller method that needs the
     * guarantee.
     */
    public function assertSelfCannotDemote(): void
    {
        $actor = $this->user();
        if ($actor === null) {
            return;
        }

        $targetUser = $this->route('user');
        if (! $targetUser instanceof User) {
            return; // POST (create) path — no `{user}` segment.
        }

        if ((int) $targetUser->id !== (int) $actor->id) {
            return; // editing someone else.
        }

        $roles = (array) $this->input('roles', []);
        if (! in_array('Administrator', $roles, true)) {
            throw ValidationException::withMessages([
                'roles' => 'You cannot remove the Administrator role from your own account.',
            ]);
        }
    }
}
