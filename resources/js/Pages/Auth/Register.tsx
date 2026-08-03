import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface CellOption { id: string; name: string; is_primary: boolean; }

/**
 * Phase 13 — Public signup page at /register.
 *
 * Compile-time inputs (from `Auth\RegisteredUserController::create()`):
 *   - `rolesForSignup`: string[] the backend allows on this form (3 entries
 *                       today: Impact_Leaders, FollowUpOfficer, Follow_UP_Admin).
 *   - `cellsList`:      { id, name, is_primary }[] for the picker's dropdown.
 *
 * Wire shape sent to /register (`RegisterInertiaRequest`):
 *   - Standard: name, email, password, password_confirmation
 *   - roles[] + active_role           (multi-role picked freely; active_role
 *                                       auto-pivots to roles[0] via server
 *                                       prepareForValidation if user drops
 *                                       the previously-active role)
 *   - impact_cell_id                   (one cell for Impact_Leaders; required
 *                                       via Rule::requiredIf when the user
 *                                       has Impact_Leaders in roles[])
 *   - leader_name / leader_phone /
 *     assistant_name / assistant_phone /
 *     welfare_officer_name / welfare_officer_phone
 *                                     (carried in the wire shape for
 *                                     Forward-compat; only seeded into the
 *                                       cell row when Impact_Leaders is in
 *                                       roles[]. Follow-up TBD.)
 *
 * Rendering shape notes
 * ---------------------
 *   - Role grid shows ONLY Impact_Leaders + Impact_Zonal_Coordinator
 *     (Phase 13 follow-up — `RoleHelper::SIGNUP_VISIBLE_ROLES`).
 *   - The cell-setup panel (cell picker + 6 leadership fields) appears
 *     ONLY when Impact_Leaders is checked. A dashed bordered empty
 *     placeholder is shown otherwise with an inline hint — phase 13
 *     follow-up reversed the prior "always visible" decision.
 *   - Errors map from `errors.X` to a single `<InputError>` per field.
 *   - The dataset for `errors.roles.*` (per-role-array) is wired so server-side
 *     `Rule::in(SIGNUP_VISIBLE_ROLES)` failures show inline.
 */
export default function Register({
    rolesForSignup,
    cellsList,
}: {
    rolesForSignup: string[];
    cellsList: CellOption[];
}) {
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        roles: [] as string[],
        active_role: '',
        impact_cell_id: '',
        leader_name: '',
        leader_phone: '',
        assistant_name: '',
        assistant_phone: '',
        welfare_officer_name: '',
        welfare_officer_phone: '',
    });

    // Mirrors the backend's `RegisterInertiaRequest::rules()`:
    // impact_cell_id is `required_if Impact_Leaders ∈ roles[]` via Rule::requiredIf.
    const requiresCell = data.roles.includes('Impact_Leaders');

    // Inertia's clearErrors is keyed to the form's own field names; the shared
    // Field helper below only ever passes one of those ids, so adapt the
    // signature once here instead of casting at every call site.
    const clearFieldError = (field: string) =>
        clearErrors(field as keyof typeof data);

    const submit: FormEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();

        // Browser autofill (Chrome saved addresses / password manager) can
        // populate the DOM inputs WITHOUT firing React's onChange, so the
        // controlled state here can lag behind what the user actually sees
        // in the fields. FormData reads the LIVE DOM values, so we merge
        // them into the payload before posting — otherwise a visibly-filled
        // (but autofilled) name/email submits as empty and the server rejects
        // the form with "The name field is required" / "The email field is
        // required" even though the user filled the fields in.
        //
        // Safe for normal typing too: for typed input the DOM value already
        // matches state, so the merge is a no-op for those fields.
        const fd = new FormData(e.currentTarget);

        // Sanitizer (2026-08-03 zonal-only signup bug): fields that are NOT
        // in the DOM return null from FormData, and `?? prev.x` fell through
        // to undefined for fields whose state had been wiped (see toggleRole
        // below) — the wire then carried the LITERAL STRING "undefined",
        // which the server's `uuid` rule on impact_cell_id rejected with an
        // invisible error (the InputError lives inside the unrendered cell
        // panel). Missing fields must collapse to '' — never "undefined".
        const toWire = (v: unknown): string =>
            v === null || v === undefined ? '' : String(v);

        setData((prev) => ({
            ...prev,
            name: toWire(fd.get('name') ?? prev.name),
            email: toWire(fd.get('email') ?? prev.email),
            password: toWire(fd.get('password') ?? prev.password),
            password_confirmation: toWire(
                fd.get('password_confirmation') ?? prev.password_confirmation,
            ),
            impact_cell_id: toWire(
                fd.get('impact_cell_id') ?? prev.impact_cell_id,
            ),
            leader_name: toWire(fd.get('leader_name') ?? prev.leader_name),
            leader_phone: toWire(fd.get('leader_phone') ?? prev.leader_phone),
            assistant_name: toWire(
                fd.get('assistant_name') ?? prev.assistant_name,
            ),
            assistant_phone: toWire(
                fd.get('assistant_phone') ?? prev.assistant_phone,
            ),
            welfare_officer_name: toWire(
                fd.get('welfare_officer_name') ?? prev.welfare_officer_name,
            ),
            welfare_officer_phone: toWire(
                fd.get('welfare_officer_phone') ?? prev.welfare_officer_phone,
            ),
        }));

        post(route('register'), {
            // onSuccess (not onFinish): a FAILED submit must keep the typed
            // password so the user can correct the offending field instead of
            // re-typing it — onFinish also fires on validation errors.
            onSuccess: () => reset('password', 'password_confirmation'),
        });
    };

    // Mirrors Admin/Users/Edit's behaviour: unchecking the previously-active
    // role would trip the server's Rule::in($roles) on active_role, so we
    // auto-pivot the active_role to roles[0] when the current active falls
    // off the grid. (Server's prepareForValidation already covers this,
    // but keeping the client optimistic avoids a brief error flicker.)
    const toggleRole = (role: string) => {
        const next = data.roles.includes(role)
            ? data.roles.filter((r) => r !== role)
            : [...data.roles, role];
        // Function-form setData with a full spread — NOT object form. Inertia
        // v2's `setData(keyOrData)` does `commitData(keyOrData)` i.e. REPLACES
        // the entire form data with the given object (verified against
        // @inertiajs/react 2.3.27). The old object form silently wiped every
        // field not listed here (name/email/password/impact_cell_id/leader_*),
        // which is exactly how the 2026-08-03 zonal-only signup bug started:
        // wiped state + the submit sanitizer gap → literal "undefined" on the
        // wire → server `uuid` rejection → invisible registration failure.
        setData((prev) => ({
            ...prev,
            roles: next,
            // Use prev.active_role (not the render-closure data.active_role)
            // inside the updater — identical today, but immune to stale
            // closure reads if React ever batches the update.
            active_role: next.includes(prev.active_role)
                ? prev.active_role
                : (next[0] ?? ''),
        }));
        // Drop stale role errors ('Pick at least one role.', 'That role is
        // not available for self-signup.') as soon as the user changes the
        // selection — the server keys array-rule failures per-index
        // (roles.0, roles.1, …), so clear every roles-prefixed key.
        const roleErrorKeys = Object.keys(errors).filter(
            (k) => k === 'roles' || k.startsWith('roles.'),
        );
        if (roleErrorKeys.length > 0) {
            clearErrors(...(roleErrorKeys as (keyof typeof data)[]));
        }
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <div className="space-y-6">
                {/* Heading */}
                <div className="space-y-1.5">
                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Create your account
                    </h2>
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        Register on the Outreach Portal — pick the role(s) that match your work and we'll set up your dashboard.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    {/* (errors.roles maps to errorsForArr() below — see helper.) */}
                    {/* ── Account basics ─────────────────────────────────────── */}
                    <Field
                        id="name"
                        label="Full name"
                        value={data.name}
                        onChange={(v) => setData('name', v)}
                        onClearError={clearFieldError}
                        required
                        autoComplete="name"
                        placeholder="Jane Doe"
                        error={errors.name}
                    />
                    <Field
                        id="email"
                        label="Email address"
                        type="email"
                        value={data.email}
                        onChange={(v) => setData('email', v)}
                        onClearError={clearFieldError}
                        required
                        autoComplete="username"
                        placeholder="you@impact.test"
                        error={errors.email}
                    />

                    {/* Password */}
                    <div className="space-y-1.5">
                        <InputLabel htmlFor="password" value="Password" />
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400 dark:text-gray-500">
                                <svg viewBox="0 0 24 24" fill="currentColor" className="h-4 w-4" aria-hidden="true">
                                    <path fillRule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clipRule="evenodd" />
                                </svg>
                            </span>
                            <TextInput
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                value={data.password}
                                className="block w-full pl-10 pr-10"
                                autoComplete="new-password"
                                placeholder="Choose a strong password"
                                onChange={(e) => {
                                    setData('password', e.target.value);
                                    clearErrors('password');
                                }}
                                required
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((s) => ! s)}
                                className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:text-gray-500 dark:hover:text-gray-300 rounded"
                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                            >
                                {showPassword ? (
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                        <path d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.244 7.244L21 21m-3.878-3.878-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                ) : (
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                )}
                            </button>
                        </div>
                        <InputError message={errors.password} className="mt-1.5" />
                    </div>

                    {/* Confirm password */}
                    <Field
                        id="password_confirmation"
                        label="Confirm password"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(v) => setData('password_confirmation', v)}
                        onClearError={clearFieldError}
                        required
                        autoComplete="new-password"
                        placeholder="Re-enter your password"
                        error={errors.password_confirmation}
                    />

                    {/* ── Roles (Phase 13: multi-role checkbox grid) ─────────────── */}
                    <div className="space-y-2 rounded-lg border border-gray-200 bg-gray-50/40 p-4 dark:border-gray-700 dark:bg-gray-900/30" data-testid="register-roles-block">
                        <div className="flex items-center justify-between gap-2">
                            <InputLabel value="Pick your role(s)" />
                            <span className="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                {data.roles.length} of {rolesForSignup.length} selected
                            </span>
                        </div>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Choose one or more. You can switch between dashboards after login (e.g. volunteer + officer).
                        </p>
                        <div className="flex flex-wrap gap-2.5" data-testid="register-roles-grid">
                            {rolesForSignup.map((role) => {
                                const checked = data.roles.includes(role);
                                // Auto-sized role cards (2026-08-03): cards are `inline-flex` and grow
                                // with their label (min-w 180px, max-w-full) instead of being squeezed
                                // into a fixed 3-column grid — long names like Impact_Zonal_Coordinator
                                // previously overflowed the border. The label shown is the human-friendly
                                // form ("Impact Zonal Coordinator"); the checkbox value stays the
                                // canonical snake_case role name, so the wire + server validation are
                                // untouched. No caption lines today; if captions return, re-introduce a
                                // per-role description map below the checkbox row.
                                return (
                                    <label
                                        key={role}
                                        className={`inline-flex cursor-pointer items-center gap-2.5 rounded-xl border px-[18px] py-[14px] text-sm transition-colors min-w-[180px] max-w-full shrink-0 whitespace-normal sm:whitespace-nowrap ${
                                            checked
                                                ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-700/50 dark:bg-indigo-900/30'
                                                : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                                        }`}
                                        data-testid={`register-role-${role}`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={() => toggleRole(role)}
                                            className="h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                        />
                                        <span className="font-medium text-gray-900 dark:text-gray-100">
                                            {friendlyRoleName(role)}
                                        </span>
                                    </label>
                                );
                            })}
                        </div>
                        <InputError message={errorsForArr(errors, 'roles')} className="mt-1.5" />
                    </div>

                    {/* ── Cell-setup panel (Phase 13 follow-up): ONLY revealed ─── */}
                    {/* when Impact_Leaders is in roles[]. Impact_Zonal_Coordinator  */}
                    {/* signups don't pick a cell at signup time; Admin assigns the  */}
                    {/* zone post-signup. Replaces the prior "always-visible"       */}
                    {/* layout.                                                     */}
                    {requiresCell ? (
                        <div
                            className="space-y-5 rounded-lg border border-gray-200 bg-gray-50/40 p-4 dark:border-gray-700 dark:bg-gray-900/30"
                            data-testid="register-cell-setup-block"
                        >
                            <div className="space-y-1">
                                <h3 className="text-sm font-semibold text-gray-900 dark:text-white">Cell setup</h3>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Pick the primary cell you lead, then capture your <em>leadership team</em> roster. These seed the cell's own Leadership Team card on submit.
                                </p>
                            </div>

                            {/* Cell picker */}
                            <div className="space-y-1.5" data-testid="register-impact-cell-block">
                                <InputLabel htmlFor="impact_cell_id" value="Impact Cell" />
                                <select
                                    id="impact_cell_id"
                                    name="impact_cell_id"
                                    value={data.impact_cell_id}
                                    onChange={(e) => {
                                        setData('impact_cell_id', e.target.value);
                                        clearErrors('impact_cell_id');
                                    }}
                                    required
                                    aria-describedby="register-impact-cell-help"
                                    className={`block w-full rounded-md border bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 dark:bg-gray-800 dark:text-gray-100 ${
                                        errors.impact_cell_id
                                            ? 'border-red-300 focus:border-red-500 focus:ring-red-500 dark:border-red-700'
                                            : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700'
                                    }`}
                                    data-testid="register-impact-cell"
                                >
                                    <option value="">— Pick the cell you lead —</option>
                                    {cellsList.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}{c.is_primary ? ' (primary)' : ''}
                                        </option>
                                    ))}
                                </select>
                                <p id="register-impact-cell-help" className="text-xs text-gray-500 dark:text-gray-400">
                                    Required — pick the cell you lead. Assigned-leader info seeds the cell's leadership roster on save.
                                </p>
                                <InputError message={errors.impact_cell_id} className="mt-1.5" />
                            </div>

                            {/* Leadership team roster */}
                            <div className="space-y-3" data-testid="register-leadership-team-block">
                                <InputLabel value="Leadership team" />
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Optional. The cell record uses these as the initial roster; admin can re-edit them later from the cell's own page.
                                </p>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <Field
                                        id="leader_name"
                                        label="Leader name"
                                        value={data.leader_name}
                                        onChange={(v) => setData('leader_name', v)}
                                        onClearError={clearFieldError}
                                        placeholder="Adebayo Smith"
                                        error={errors.leader_name}
                                        inputClassName="bg-white dark:bg-gray-800"
                                        required
                                    />
                                    <Field
                                        id="leader_phone"
                                        label="Leader phone"
                                        value={data.leader_phone}
                                        onChange={(v) => setData('leader_phone', v)}
                                        onClearError={clearFieldError}
                                        placeholder="+234 800 000 0001"
                                        error={errors.leader_phone}
                                        inputClassName="bg-white dark:bg-gray-800"
                                        required
                                    />
                                    <Field
                                        id="assistant_name"
                                        label="Assistant name"
                                        value={data.assistant_name}
                                        onChange={(v) => setData('assistant_name', v)}
                                        onClearError={clearFieldError}
                                        placeholder="Jane Doe"
                                        error={errors.assistant_name}
                                        inputClassName="bg-white dark:bg-gray-800"
                                    />
                                    <Field
                                        id="assistant_phone"
                                        label="Assistant phone"
                                        value={data.assistant_phone}
                                        onChange={(v) => setData('assistant_phone', v)}
                                        onClearError={clearFieldError}
                                        placeholder="+234 800 000 0002"
                                        error={errors.assistant_phone}
                                        inputClassName="bg-white dark:bg-gray-800"
                                    />
                                    <Field
                                        id="welfare_officer_name"
                                        label="Welfare officer name"
                                        value={data.welfare_officer_name}
                                        onChange={(v) => setData('welfare_officer_name', v)}
                                        onClearError={clearFieldError}
                                        placeholder="Mary Johnson"
                                        error={errors.welfare_officer_name}
                                        inputClassName="bg-white dark:bg-gray-800"
                                    />
                                    <Field
                                        id="welfare_officer_phone"
                                        label="Welfare officer phone"
                                        value={data.welfare_officer_phone}
                                        onChange={(v) => setData('welfare_officer_phone', v)}
                                        onClearError={clearFieldError}
                                        placeholder="+234 800 000 0003"
                                        error={errors.welfare_officer_phone}
                                        inputClassName="bg-white dark:bg-gray-800"
                                    />
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div
                            className="rounded-lg border border-dashed border-gray-300 bg-gray-50/40 p-4 dark:border-gray-700 dark:bg-gray-900/30"
                            data-testid="register-cell-setup-empty"
                        >
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                <span className="font-medium text-gray-700 dark:text-gray-200">Cell &amp; leadership-team setup</span>{' '}
                                appears here once you tick the <span className="font-medium text-gray-700 dark:text-gray-200">Impact Leaders</span> role above. Hidden for other roles —
                                an{' '}
                                <span className="font-medium text-gray-700 dark:text-gray-200">Impact Zonal Coordinator</span> signup is cell-less at this step (Admin assigns your zone later).
                            </p>
                            {/* 2026-08-03: server errors on the hidden cell fields were
                                invisible (their InputErrors live inside the unrendered
                                panel above). Surface them here so a rejection is never
                                a silent "nothing happened". */}
                            {HIDDEN_CELL_FIELDS.filter((f) => errors[f]).map((f) => (
                                <InputError key={f} message={errors[f]} className="mt-1.5" />
                            ))}
                        </div>
                    )}

                    {/* Footer + Submit */}
                    <div className="flex items-center justify-between gap-3 pt-1">
                        <Link
                            href={route('login')}
                            data-testid="register-login-link"
                            className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            Already registered? Sign in
                        </Link>
                    </div>

                    <PrimaryButton
                        className="w-full justify-center !bg-indigo-600 !px-4 !py-3 !text-sm !tracking-wide !normal-case shadow-sm transition-all !hover:bg-indigo-700 !focus:bg-indigo-700 !focus:shadow-md !active:bg-indigo-800 dark:!bg-indigo-500 dark:!hover:bg-indigo-400 dark:!focus:bg-indigo-400"
                        disabled={processing}
                    >
                        {processing ? (
                            <span className="inline-flex items-center gap-2">
                                <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                Creating account…
                            </span>
                        ) : (
                            'Create account'
                        )}
                    </PrimaryButton>
                </form>
            </div>
        </GuestLayout>
    );
}

/**
 * 2026-08-03 — the 7 optional cell-setup fields whose InputErrors render
 * ONLY inside the Impact_Leaders cell panel. When that panel is unmounted
 * (e.g. Impact_Zonal_Coordinator-only signup), a server rejection on one of
 * these fields would otherwise be completely invisible to the user. The
 * register-cell-setup-empty placeholder renders their errors as a fallback.
 */
const HIDDEN_CELL_FIELDS = [
    'impact_cell_id',
    'leader_name',
    'leader_phone',
    'assistant_name',
    'assistant_phone',
    'welfare_officer_name',
    'welfare_officer_phone',
] as const;

/**
 * Phase 13 — Laravel keys per-array-rule failures at `${field}.0`,
 * `${field}.1`, … NOT at `field`. The role grid uses `roles[]` so a
 * rejection (e.g. `Rule::in(SIGNUP_VISIBLE_ROLES)` denying Administrator)
 * comes back as `errors['roles.0']`. Without this flatten helper the
 * `<InputError message={errors.roles}>` would silently swallow it.
 *
 * First scan looks for the direct `field` key (covers the case where
 * the Form Request failed the `roles` array-level rule itself), then
 * falls through to per-index keys. Returns `undefined` if no match —
 * `<InputError>` already treats `undefined` as "no message".
 */
/**
 * 2026-08-03 — human-friendly role label for the signup cards.
 *
 * Role names are canonical snake_case on the wire (Impact_Zonal_Coordinator)
 * and must stay that way in `value`/checkbox payloads, but the UI shows a
 * readable label ("Impact Zonal Coordinator"). Handles snake_case AND
 * camelCase boundaries + repeated underscores, so it stays correct for
 * every role in RoleHelper::ROLE_NAMES / SIGNUP_VISIBLE_ROLES without
 * a hardcoded lookup table.
 *
 *   Impact_Zonal_Coordinator -> Impact Zonal Coordinator
 *   Impact_Leaders           -> Impact Leaders
 *   FollowUpOfficer          -> Follow Up Officer
 *   Follow_UP_Admin          -> Follow UP Admin
 */
function friendlyRoleName(role: string): string {
    return role
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2') // camelCase boundary -> space
        .replace(/_+/g, ' ') // underscores -> spaces
        .replace(/\s+/g, ' ')
        .trim();
}

function errorsForArr(errors: Record<string, string>, field: string): string | undefined {
    if (errors[field]) return errors[field];
    for (let i = 0; i < 32; i++) {
        const k = `${field}.${i}`;
        if (errors[k]) return errors[k];
    }
    return undefined;
}

/**
 * Tiny inline helper to dedupe the dense `<InputLabel + TextInput + InputError>`
 * triple across the 9 text-input fields (4 account basics + 6 leadership team).
 * Keeps the JSX above readable.
 */
function Field({
    id,
    label,
    value,
    onChange,
    onClearError,
    type = 'text',
    required = false,
    autoComplete,
    placeholder,
    error,
    inputClassName,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (v: string) => void;
    onClearError?: (field: string) => void;
    type?: string;
    required?: boolean;
    autoComplete?: string;
    placeholder?: string;
    error?: string;
    inputClassName?: string;
}) {
    return (
        <div className="space-y-1.5">
            <InputLabel htmlFor={id} value={label} />
            <TextInput
                id={id}
                name={id}
                type={type}
                value={value}
                onChange={(e) => {
                    onChange(e.target.value);
                    // Inertia keeps validation errors on screen until the next
                    // submit — clear the field's own error as soon as the user
                    // edits it, so "required" messages don't linger after the
                    // user has already filled the field in.
                    onClearError?.(id);
                }}
                className={`block w-full ${inputClassName ?? ''}`}
                autoComplete={autoComplete}
                placeholder={placeholder}
                required={required}
            />
            <InputError message={error} className="mt-1.5" />
        </div>
    );
}
