import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import RoleBadge from '@/Components/RoleBadge';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface CellOption { id: string; name: string; is_primary: boolean; }

interface AdminUsersEditPageProps {
    user: {
        id: number;
        name: string;
        email: string;
        email_verified_at: string | null;
        roles: string[];
        has_multiple_roles: boolean;
        active_role: string | null;
        active_group: string | null;
        last_seen_at: string | null;
        joined_at: string | null;
        deleted_at: string | null;
        trashed: boolean;
        // Phase 13 — assigned Impact Cell (FK for Impact_Leaders users).
        // Nullable because FollowUpOfficer / Follow_UP_Admin signups don't
        // carry one; the Edit page's select reads this value as the
        // dropdown's pre-selected option.
        impact_cell_id: string | null;
        zonal_impact_cell_ids: string[];
    };
    rolesForNew: string[];
    // Phase 13 — full cell list for the "Assigned Impact Cell" select.
    cellsList: CellOption[];
    isSelf: boolean;
    isTrashed: boolean;
    deletedAt: string | null;
    activeRole: string | null;
    currentUserId: number;
}

const peopleEditIcon = (
    <>
        <circle cx="12" cy="8" r="4" />
        <path d="M4 21a8 8 0 0 1 16 0" />
        <path d="M15 8a4 4 0 1 0-6 0" strokeDasharray="2 2" />
    </>
);

/**
 * Phase 06e+3 — Admin/Users/Edit page.
 *
 * Comprehensive editor for an existing user. Loaded from the "Edit"
 * button in the Index page Actions column. Mirrors Guests/Edit.tsx
 * layout; intentionally NOT a modal (the form's full state — name /
 * email / roles / active_role / optional password — doesn't fit in a
 * HeadlessUI Dialog without crowding).
 *
 * Form wiring
 * -----------
 * - Server pre-fills all fields from the persisted User model.
 * - Roles grid is a checkbox set; Administrator is DISABLED when this
 *   is a self-edit (the actor cannot demote themselves out of the
 *   admin role — `AdminUserRequest::assertSelfCannotDemote` enforces
 *   this server-side too, but the disabled checkbox is the friendlier
 *   surface).
 * - Password is OMITTED from the `data` shape the controller ships;
 *   source-of-truth comes from the controller's update() (only writes
 *   a new password if the user submits a non-empty value).
 * - Active-role dropdown auto-tracks the roles grid: unchecking the
 *   active role auto-pivots to the first remaining role (mirrors Add
 *   modal behaviour).
 * - When `isTrashed`, the entire form is locked except for a single
 *   "Restore user" button shown in a banner at the top of the page.
 */
export default function AdminUsersEditPage({
    user,
    rolesForNew,
    cellsList,
    isSelf,
    isTrashed,
    activeRole,
    currentUserId,
}: AdminUsersEditPageProps) {
    const { data, setData, put, processing, errors, reset } = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        roles: user.roles,
        // Phase 13 — pre-fill assigned cell from the UserResource's
        // `impact_cell_id` field. Empty string when null so the option
        // list reads "— No cell —" via select placeholder.
        impact_cell_id: user.impact_cell_id ?? '',
        active_role: user.active_role ?? '',
        zonal_impact_cell_ids: user.zonal_impact_cell_ids ?? [],
    });

    // Role-specific cell requirements mirror the controller's server-side checks.
    const needsAssignedCell = data.roles.includes('Impact_Leaders');
    const needsZonalCells = data.roles.includes('Impact_Zonal_Coordinator');

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.users.update', user.id), {
            preserveScroll: true,
        });
    };

    const toggleRole = (role: string) => {
        const next = data.roles.includes(role)
            ? data.roles.filter((r) => r !== role)
            : [...data.roles, role];
        setData('roles', next);
        if (! next.includes(data.active_role)) {
            setData('active_role', next[0] ?? '');
        }
        if (! next.includes('Impact_Leaders')) {
            setData('impact_cell_id', '');
        }
        if (! next.includes('Impact_Zonal_Coordinator')) {
            setData('zonal_impact_cell_ids', []);
        }
    };

    const toggleZonalCell = (cellId: string) => {
        setData(
            'zonal_impact_cell_ids',
            data.zonal_impact_cell_ids.includes(cellId)
                ? data.zonal_impact_cell_ids.filter((id) => id !== cellId)
                : [...data.zonal_impact_cell_ids, cellId],
        );
    };

    const onRestore = () => {
        if (! confirm(`Restore user ${user.name}? Their Spatie roles + active_role stay intact.`)) {
            return;
        }
        router.patch(route('admin.users.restore', user.id), {}, { preserveScroll: true });
    };

    return (
        <AdminDashboardLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                            Administrator · Users · Edit
                        </p>
                        <h2 className="mt-1 flex items-center gap-3 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            <span>{user.name}</span>
                            <RoleBadge role={user.active_role} />
                            {isSelf && (
                                <span className="rounded bg-indigo-100 px-2 py-0.5 text-xs font-semibold uppercase text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                    Editing yourself
                                </span>
                            )}
                        </h2>
                        <p className="mt-1 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <span className="font-mono">{user.email}</span>
                            {user.email_verified_at
                                ? <span className="rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300">verified</span>
                                : <span className="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-300">unverified</span>}
                            <span>·</span>
                            <span>Joined <RelativeDate iso={user.joined_at} /></span>
                            <span>·</span>
                            <span>Last seen <RelativeDate iso={user.last_seen_at} /></span>
                        </p>
                    </div>
                    <Link
                        href={route('admin.users.index')}
                        className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 dark:text-indigo-400"
                        preserveState
                    >
                        ← Back to Users
                    </Link>
                </div>
            }
        >
            <Head title={`Edit · ${user.name} · Admin`} />

            {isTrashed && (
                <div
                    className="mb-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800/40 dark:bg-amber-900/30 dark:text-amber-200"
                    data-testid="users-edit-trashed-banner"
                    role="alert"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                        <path d="M12 9v4" />
                        <path d="M12 17h.01" />
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    </svg>
                    <div className="flex-1">
                        <p className="font-semibold">This user has been deleted.</p>
                        <p className="mt-1">
                            Removed on {user.deleted_at ? new Date(user.deleted_at).toLocaleString() : '(unknown)'}. Restoring re-enables the account with the original Spatie role set intact and clears any stale password-reset tokens.
                        </p>
                        <button
                            type="button"
                            onClick={onRestore}
                            className="mt-3 inline-flex items-center gap-2 rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                            data-testid="users-edit-restore-button"
                        >
                            Restore user
                        </button>
                    </div>
                </div>
            )}

            {!isTrashed && (
                <form onSubmit={submit} className="space-y-6" data-testid="users-edit-form">
                    {/* Name + Email */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-1.5">
                            <InputLabel htmlFor="users-edit-name" value="Full name" />
                            <TextInput
                                id="users-edit-name"
                                name="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="block w-full"
                                autoComplete="name"
                                required
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="space-y-1.5">
                            <InputLabel htmlFor="users-edit-email" value="Email address" />
                            <TextInput
                                id="users-edit-email"
                                type="email"
                                name="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="block w-full"
                                autoComplete="email"
                                required
                            />
                            <InputError message={errors.email} />
                        </div>
                    </div>

                    {/* Password + Confirmation */}
                    <div className="rounded-lg border border-gray-200 bg-gray-50/40 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <p className="text-sm font-semibold text-gray-900 dark:text-white">Set a new password (optional)</p>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Leave both fields blank to keep the current password. New passwords must satisfy the application default rules.
                        </p>
                        <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <InputLabel htmlFor="users-edit-password" value="New password" />
                                <TextInput
                                    id="users-edit-password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="block w-full"
                                    autoComplete="new-password"
                                    placeholder="Min 8 characters"
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="space-y-1.5">
                                <InputLabel htmlFor="users-edit-password-confirmation" value="Confirm new password" />
                                <TextInput
                                    id="users-edit-password-confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    className="block w-full"
                                    autoComplete="new-password"
                                    placeholder="Re-enter"
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>
                        </div>
                    </div>

                    {/* Roles grid */}
                    <div className="space-y-2">
                        <InputLabel value="Roles (at least one)" />
                        <div
                            className="grid grid-cols-1 gap-1.5 sm:grid-cols-2"
                            data-testid="users-edit-roles-grid"
                        >
                            {rolesForNew.map((role) => {
                                const checked = data.roles.includes(role);
                                const isSelfAdministratorLock = isSelf && role === 'Administrator';
                                return (
                                    <label
                                        key={role}
                                        className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors ${
                                            checked
                                                ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-700/50 dark:bg-indigo-900/30'
                                                : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                                        } ${isSelfAdministratorLock ? 'opacity-80 ring-1 ring-indigo-200 dark:ring-indigo-700/40' : ''}`}
                                    >
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            disabled={isSelfAdministratorLock}
                                            onChange={() => toggleRole(role)}
                                            aria-describedby={isSelfAdministratorLock ? `users-edit-role-help-${role}` : undefined}
                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900"
                                            data-testid={`users-edit-role-${role}`}
                                        />
                                        <span className="flex-1 truncate text-gray-800 dark:text-gray-200">{role}</span>
                                        {isSelfAdministratorLock && (
                                            <span
                                                id={`users-edit-role-help-${role}`}
                                                className="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                                                title="Required for your own account"
                                            >
                                                Required
                                            </span>
                                        )}
                                        {checked && !isSelfAdministratorLock && <RoleBadge role={role} />}
                                    </label>
                                );
                            })}
                        </div>
                        <InputError message={errors.roles} />
                    </div>

                    {/* Active role dropdown */}
                    <div className="space-y-1.5">
                        <InputLabel htmlFor="users-edit-active-role" value="Active role" />
                        <select
                            id="users-edit-active-role"
                            name="active_role"
                            value={data.active_role}
                            onChange={(e) => setData('active_role', e.target.value)}
                            className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            data-testid="users-edit-active-role"
                            disabled={data.roles.length === 0}
                            required
                        >
                            <option value="" disabled>Pick a role above first…</option>
                            {data.roles.map((r) => (
                                <option key={r} value={r}>{r}</option>
                            ))}
                        </select>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Drives the column-edit policy and the top-bar role badge.
                        </p>
                        <InputError message={errors.active_role} />
                    </div>

                    {needsAssignedCell && (/* Assigned Impact Cell — Phase 13.
                      * Required when Impact_Leaders is in roles[] (server
                      * checks again via assertLeaderHasCell). Same shape
                      * as the public signup cell picker but with an
                      * explicit "No cell assigned" blank-option (signup
                      * has its own "— Pick the cell —" copy keyed off
                      * `requiresCell`).
                      *
                      * Layout order differs intentionally from the
                      * Auth/Register signup form: here the cell comes
                      * AFTER the active-role dropdown because admin
                      * edits typically start at the role layer and the
                      * audit history (active role pinned by admin) is
                      * more visible up top. */
                    <div className="space-y-1.5">
                        <InputLabel htmlFor="users-edit-impact-cell" value="Assigned Impact Cell" />
                        <select
                            id="users-edit-impact-cell"
                            name="impact_cell_id"
                            value={data.impact_cell_id}
                            onChange={(e) => setData('impact_cell_id', e.target.value)}
                            className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            data-testid="users-edit-impact-cell"
                            aria-describedby="users-edit-impact-cell-help"
                            required={needsAssignedCell}
                        >
                            <option value="">
                                {needsAssignedCell ? '— Pick the cell this leader heads —' : '— No cell assigned —'}
                            </option>
                            {cellsList.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}{c.is_primary ? ' (primary)' : ''}
                                </option>
                            ))}
                        </select>
                        <p id="users-edit-impact-cell-help" className="text-xs text-gray-500 dark:text-gray-400">
                            {needsAssignedCell
                                ? 'Required for Impact Leaders — admin assigns the cell the leader heads.'
                                : 'Optional for non-Impact_Leaders users. Leave blank for FollowUpOfficer / Follow_UP_Admin.'}
                        </p>
                        <InputError message={errors.impact_cell_id} />
                    </div>
                    )}

                    {needsZonalCells && (
                        <div className="space-y-2 rounded-lg border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-800/50 dark:bg-amber-900/20">
                            <InputLabel value="Impact Cells covered by this Zonal Coordinator" />
                            <div className="grid max-h-60 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2" data-testid="users-edit-zonal-cells">
                                {cellsList.map((cell) => {
                                    const checked = data.zonal_impact_cell_ids.includes(cell.id);
                                    return (
                                        <label key={cell.id} className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors ${checked ? 'border-amber-300 bg-amber-100/70 dark:border-amber-700 dark:bg-amber-900/40' : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800'}`}>
                                            <input
                                                type="checkbox"
                                                checked={checked}
                                                onChange={() => toggleZonalCell(cell.id)}
                                                className="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-900"
                                                data-testid={`users-edit-zonal-cell-${cell.id}`}
                                            />
                                            <span className="truncate text-gray-800 dark:text-gray-200">{cell.name}</span>
                                        </label>
                                    );
                                })}
                            </div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Required for Zonal Coordinators. Select one or more cells.</p>
                            <InputError message={errors.zonal_impact_cell_ids} />
                        </div>
                    )}

                    {/* Footer */}
                    <div className="flex items-center justify-end gap-2 pt-2">
                        <SecondaryButton
                            type="button"
                            onClick={() => reset()}
                            disabled={processing}
                        >
                            Reset
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={processing || data.roles.length === 0 || (needsZonalCells && data.zonal_impact_cell_ids.length === 0) || (needsAssignedCell && !data.impact_cell_id)}
                            data-testid="users-edit-submit"
                        >
                            {processing ? 'Saving…' : 'Save changes'}
                        </PrimaryButton>
                    </div>
                </form>
            )}
        </AdminDashboardLayout>
    );
}

/**
 * Tiny inline date-format helper for the header subtitle. Mirrors
 * RelativeTime but always renders an absolute date (no "min ago"
 * abbreviation) so the header line doesn't change length when the
 * user revisits the page. Falls back to "—" for null.
 */
function RelativeDate({ iso }: { iso: string | null }) {
    if (! iso) return <span className="text-gray-400">—</span>;
    const d = new Date(iso);
    if (isNaN(d.getTime())) return <span className="text-gray-400">—</span>;
    return (
        <span title={d.toISOString()} className="font-mono">
            {d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}
        </span>
    );
}
