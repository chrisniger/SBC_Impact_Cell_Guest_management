<?php

namespace App\Http\Requests;

use App\Support\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guest form request — Phase 04.
 *
 * The single source of truth for column-level writes (per
 * bridge § 6 + Implementation/03_Three_User_Groups.md).
 *
 * `prepareForValidation()` runs BEFORE the validator inspects the data, so
 * banned keys are removed before validation — meaning a FollowUpOfficer who
 * attempts to POST `impact_status` will see it silently dropped (the validator
 * never sees it, so it never appears in the validated data, and the column
 * policy cannot be bypassed by misconfigured frontend code).
 *
 * The stripping is delegated to `RoleHelper::stripDisallowed($role, $this->all())`
 * which iterates `App\Support\RoleHelper::GROUP_GUEST_OWNER` per the user's
 * active role. Administrator is a pass-through; unknown-role / no-role users
 * get their entire body stripped (defensive default).
 *
 * ⚠️ DO NOT move the strip into `rules()` — it runs AFTER validation routing
 * is established, and the validator would have already seen the keys.
 *
 * After validation, the cross-cutting rule (`contacted_status != AvailableForVisit`
 * nullifies `visitation_status` + `feedback`) is applied in the CONTROLLER
 * (not here) because it operates on already-validated data and is a business
 * rule, not an input-validation rule.
 */
class GuestRequest extends FormRequest
{
    /**
     * Always authorized within the controller's policy gate. The Form Request
     * intentionally does not gate by role — that's the controller's job
     * (via `$this->authorize('create', Guest::class)`). This keeps the request
     * re-usable for `update` / `reassign` etc. with different policies.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Strip disallowed columns BEFORE validation.
     *
     * Single source of truth: `RoleHelper::stripDisallowed()`.
     * Never inline `array_filter` here — see bridge § 6.
     */
    protected function prepareForValidation(): void
    {
        $role = $this->user()?->activeRole();

        $stripped = RoleHelper::stripDisallowed($role, $this->all());

        $this->replace($stripped);
    }

    /**
     * Validation rules. Only the columns that ANY group can write are detailed
     * here — by the time `rules()` runs, `prepareForValidation()` has already
     * removed everything else, so missing keys here just mean "this role
     * can't write that field".
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Core (admin-only when written through this Form Request — all groups have V)
            'guest_name' => ['required', 'string', 'max:255'],
            'date'       => ['nullable', 'date'],
            'event'      => ['nullable', 'string', 'max:255'],
            'event_other' => ['nullable', 'string', 'max:255'],
            'source'     => ['nullable', 'string', 'max:255'],

            // Demographics (Follow Up Officer group)
            'gender'         => ['nullable', 'string', 'max:32'],
            'marital_status' => ['nullable', 'string', 'max:32'],
            'age'            => ['nullable', 'string', 'max:16'],   // String per v2 schema
            'phone'          => ['nullable', 'string', 'max:32'],
            'address'        => ['nullable', 'string', 'max:255'],

            // Impact Cell group
            'nearest_impact_cell_id' => ['nullable', 'uuid', 'exists:impact_cells,id'],
            'impact_status'          => ['nullable', 'string', 'max:64'],

            // Follow Up Officer group — contact + visitation
            'contacted_status'  => ['nullable', 'string', 'max:64'],
            'join_when'         => ['nullable', 'string', 'max:64'],
            'days_available'    => ['nullable', 'string', 'max:255'],
            'comments'          => ['nullable', 'string'],
            'visited'           => ['nullable', 'boolean'],
            'visited_at'        => ['nullable', 'string', 'max:255'],
            'indicated_to_join' => ['nullable', 'string', 'max:255'],
            'visitation_status' => ['nullable', 'string', 'max:64'],
            'feedback'          => ['nullable', 'string'],

            // Follow Up Team group
            'follow_up_status'   => ['nullable', 'string', 'max:64'],
            'follow_up_contacts' => ['nullable', 'array', 'max:3'],

            // Assignment (admin-only via the controller's policy — stripped here too)
            'follow_officer_id' => ['nullable', 'uuid', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'guest_name.required'         => 'A guest name is required.',
            'nearest_impact_cell_id.exists' => 'Selected Impact Cell does not exist.',
            'follow_officer_id.exists'    => 'Selected Follow Up Officer does not exist.',
            'follow_up_contacts.max'      => 'Follow Up Contacts supports a maximum of 3 sections.',
        ];
    }
}
