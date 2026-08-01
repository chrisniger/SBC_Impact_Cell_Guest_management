import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import RelativeTime from '@/Components/RelativeTime';
import RoleBadge from '@/Components/RoleBadge';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface UsersPaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles: string[];
    has_multiple_roles: boolean;
    active_role: string | null;
    active_group: string | null;
    impact_cell_id: string | null;
    zonal_impact_cell_ids: string[];
    last_seen_at: string | null;
    joined_at: string | null;
}

interface PaginatedUsers {
    data: UserRow[];
    links: UsersPaginationLink[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
}

interface CellOption { id: string; name: string; is_primary: boolean; }

interface AdminUsersPageProps {
    users: PaginatedUsers;
    canCreate: boolean;
    activeRole: string | null;
    rolesForNew: string[];
    cellsList: CellOption[];
    currentUserId: number;
    flash?: { success?: string; error?: string } | null;
}

const peopleIconPath = (
    <>
        <circle cx="12" cy="8" r="4" />
        <path d="M4 21a8 8 0 0 1 16 0" />
    </>
);

const plusIconPath = <><path d="M12 5v14" /><path d="M5 12h14" /></>;

export default function AdminUsersIndex({
    users,
    canCreate,
    activeRole,
    rolesForNew,
    cellsList,
    currentUserId,
    flash,
}: AdminUsersPageProps) {
    const [addOpen, setAddOpen] = useState<boolean>(false);
    const [searchValue, setSearchValue] = useState<string>('');

    return (
        <AdminDashboardLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                            Administrator · Users
                        </p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Users management
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Active role: <span className="font-mono">{activeRole ?? '—'}</span> · {users.meta.total} total
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(
                                    route('admin.users.index'),
                                    { search: searchValue || undefined },
                                    { preserveState: true, replace: true },
                                );
                            }}
                            className="relative"
                        >
                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    <circle cx="11" cy="11" r="7" />
                                    <line x1="20" y1="20" x2="16.65" y2="16.65" />
                                </svg>
                            </span>
                            <input
                                type="search"
                                value={searchValue}
                                onChange={(e) => setSearchValue(e.target.value)}
                                placeholder="Search by name or email"
                                className="block w-full min-w-[260px] rounded-md border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm shadow-sm transition-colors placeholder:text-gray-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500"
                                data-testid="users-search"
                            />
                        </form>
                        {canCreate && (
                            <PrimaryButton
                                type="button"
                                onClick={() => setAddOpen(true)}
                                data-testid="users-add-open"
                                className="inline-flex items-center gap-2"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    {plusIconPath}
                                </svg>
                                Add user
                            </PrimaryButton>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Users · Admin" />

            {/* Flash messages from the controller's with('success', ...). */}
            {flash?.success && (
                <div
                    className="mb-4 flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800/40 dark:bg-green-900/30 dark:text-green-200"
                    data-testid="users-flash-success"
                    role="status"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                        <path d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{flash.success}</span>
                </div>
            )}
            {flash?.error && (
                <div
                    className="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800/40 dark:bg-red-900/30 dark:text-red-200"
                    data-testid="users-flash-error"
                    role="alert"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <span>{flash.error}</span>
                </div>
            )}

            <div
                className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800"
                data-testid="users-table-wrapper"
            >
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-testid="users-table">
                        <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Active Role</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Impact Cells</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Last seen</th>
                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400" data-testid="users-empty-row">
                                        No users match the current search.
                                    </td>
                                </tr>
                            )}
                            {users.data.map((u) => (
                                <UserRowItem
                                    key={u.id}
                                    user={u}
                                    cellsList={cellsList}
                                    isSelf={u.id === currentUserId}
                                    onSwitchRole={(role) => {
                                        router.patch(
                                            route('admin.users.update-role', u.id),
                                            { role },
                                            {
                                                preserveScroll: true,
                                                preserveState: false,
                                            },
                                        );
                                    }}
                                    onDelete={() => {
                                        if (confirm(`Remove user ${u.name}? This is a soft-delete (recoverable).`)) {
                                            router.delete(
                                                route('admin.users.destroy', u.id),
                                                { preserveScroll: true },
                                            );
                                        }
                                    }}
                                />
                            ))}
                        </tbody>
                    </table>
                </div>

                {users.links.length > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-gray-50/60 px-4 py-3 text-xs dark:border-gray-700 dark:bg-gray-900/40">
                        <div className="text-gray-500 dark:text-gray-400">
                            Showing <span className="font-mono">{users.meta.from ?? 0}</span>–<span className="font-mono">{users.meta.to ?? 0}</span> of <span className="font-mono">{users.meta.total}</span>
                        </div>
                        <div className="flex flex-wrap items-center gap-1">
                            {users.links.map((link, i) => (
                                link.url
                                    ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            preserveState
                                            className={`inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md px-2 text-xs font-medium transition-colors ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white shadow-sm'
                                                    : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                                    : (
                                        <span
                                            key={i}
                                            className={`inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md px-2 text-xs font-medium transition-colors ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white shadow-sm'
                                                    : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                            } pointer-events-none opacity-50`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <AddUserModal
                show={addOpen}
                onClose={() => setAddOpen(false)}
                rolesForNew={rolesForNew}
                cellsList={cellsList}
                dataTestId="users-add-modal"
            />
        </AdminDashboardLayout>
    );
}

/**
 * Single user row. Holds the inline "Switch active role" select and
 * the delete button. State is local to the row so editing one user
 * doesn't ripple through the table.
 */
function UserRowItem({
    user,
    cellsList,
    isSelf,
    onSwitchRole,
    onDelete,
}: {
    user: UserRow;
    cellsList: CellOption[];
    isSelf: boolean;
    onSwitchRole: (role: string) => void;
    onDelete: () => void;
}) {
    return (
        <tr
            className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40"
            data-testid={`user-row-${user.id}`}
        >
            <td className="px-4 py-3 text-sm">
                <div className="flex items-center gap-2">
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                        {user.name.slice(0, 1).toUpperCase()}
                    </span>
                    <div className="min-w-0">
                        <p className="truncate font-medium text-gray-900 dark:text-gray-100">{user.name}{isSelf && <span className="ms-2 rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">you</span>}</p>
                        {user.email_verified_at
                            ? <p className="text-xs text-green-600 dark:text-green-400">verified</p>
                            : <p className="text-xs text-gray-400 dark:text-gray-500">unverified</p>}
                    </div>
                </div>
            </td>
            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                <span className="font-mono text-xs">{user.email}</span>
            </td>
            <td className="px-4 py-3">
                <div className="flex flex-wrap items-center gap-1.5">
                    <RoleBadge role={user.active_role} />
                    {user.has_multiple_roles && user.active_role && (
                        <span
                            className="text-xs text-gray-500 dark:text-gray-400"
                            data-testid="assigned-roles-count"
                            title={`Also holds: ${user.roles.filter((r) => r !== user.active_role).join(', ') || ''}`}
                        >
                            +{user.roles.length - 1}
                        </span>
                    )}
                </div>
            </td>
            <td className="px-4 py-3">
                <AssignedCells
                    user={user}
                    cellsList={cellsList}
                />
            </td>
            <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                <RelativeTime date={user.last_seen_at} className="text-xs" />
            </td>
            <td className="px-4 py-3 text-right">
                <div className="flex items-center justify-end gap-2">
                    <Link
                        href={route('admin.users.edit', user.id)}
                        preserveState
                        className="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-white px-2.5 py-1.5 text-xs font-medium text-indigo-700 transition-colors hover:border-indigo-400 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-indigo-700/50 dark:bg-gray-800 dark:text-indigo-300 dark:hover:bg-indigo-900/40"
                        data-testid={`user-row-${user.id}-edit`}
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                        </svg>
                        Edit
                    </Link>
                    <select
                        defaultValue={user.active_role ?? ''}
                        onChange={(e) => {
                            const v = e.target.value;
                            if (v && v !== user.active_role) onSwitchRole(v);
                            // Reset so the same option can be picked again.
                            e.target.value = user.active_role ?? '';
                        }}
                        className="rounded-md border border-gray-300 bg-white px-2 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition-colors hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        data-testid={`user-row-${user.id}-role-select`}
                        aria-label={`Change active role for ${user.name}`}
                        disabled={user.roles.length === 0}
                    >
                        {user.roles.length === 0
                            ? <option value="">No roles</option>
                            : user.roles.map((r) => (
                                <option key={r} value={r}>{r}</option>
                            ))}
                    </select>
                    <button
                        type="button"
                        onClick={onDelete}
                        disabled={isSelf}
                        title={isSelf ? "Can't remove yourself" : 'Remove user (soft delete)'}
                        className="inline-flex items-center gap-1.5 rounded-md border border-red-200 bg-white px-2.5 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500/40 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-800/50 dark:bg-gray-800 dark:text-red-300 dark:hover:bg-red-900/30"
                        data-testid={`user-row-${user.id}-delete`}
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                            <path d="M3 6h18" />
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                            <path d="M10 11v6" />
                            <path d="M14 11v6" />
                        </svg>
                        Remove
                    </button>
                </div>
            </td>
        </tr>
    );
}

function AssignedCells({
    user,
    cellsList,
}: {
    user: UserRow;
    cellsList: CellOption[];
}) {
    const ids = Array.from(new Set([
        ...(user.roles.includes('Impact_Leaders') && user.impact_cell_id
            ? [user.impact_cell_id]
            : []),
        ...(user.roles.includes('Impact_Zonal_Coordinator')
            ? user.zonal_impact_cell_ids
            : []),
    ]));
    const names = ids.map((id) => cellsList.find((cell) => cell.id === id)?.name ?? 'Unknown cell');

    if (names.length === 0) {
        return <span className="text-xs text-gray-400 dark:text-gray-500">—</span>;
    }

    const fullLabel = names.join(', ');
    const remaining = names.length - 1;

    return (
        <div
            className="flex max-w-[220px] flex-wrap items-center gap-1"
            role="group"
            title={fullLabel}
            aria-label={`Assigned Impact Cells: ${fullLabel}`}
            data-testid={`user-row-${user.id}-impact-cells`}
        >
            <span className="inline-flex max-w-[170px] truncate rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                {names[0]}
            </span>
            {remaining > 0 && (
                <span className="inline-flex rounded-md bg-gray-100 px-1.5 py-1 text-[11px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    +{remaining}
                </span>
            )}
        </div>
    );
}

/**
 * Add User modal — opens via `setAddOpen(true)` from the header CTA.
 *
 * Form fields:
 *   - name (required)
 *   - email (required, unique)
 *   - role[] (multi-checkbox; client requires at least one before submit)
 *   - active_role (single dropdown; auto-populates from the first role)
 *   - impact_cell_id (single cell when Impact_Leaders is selected)
 *   - zonal_impact_cell_ids (multiple cells when Impact_Zonal_Coordinator is selected)
 *   - password (required, must satisfy Laravel's Password::defaults())
 *   - password_confirmation (must match)
 *
 * On submit success, Inertia replaces the page with the new table —
 * we don't need optimistic state.
 */
function AddUserModal({
    show,
    onClose,
    rolesForNew,
    cellsList,
    dataTestId,
}: {
    show: boolean;
    onClose: () => void;
    rolesForNew: string[];
    cellsList: CellOption[];
    dataTestId?: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        roles: [] as string[],
        active_role: '' as string,
        impact_cell_id: '' as string,
        zonal_impact_cell_ids: [] as string[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    const toggleRole = (role: string) => {
        const next = data.roles.includes(role)
            ? data.roles.filter((r) => r !== role)
            : [...data.roles, role];
        setData('roles', next);
        // If the active_role got unchecked and it's still set, drop it
        // to the first remaining role (or empty).
        if (!next.includes(data.active_role)) {
            setData('active_role', next[0] ?? '');
        }
        if (!next.includes('Impact_Leaders')) {
            setData('impact_cell_id', '');
        }
        if (!next.includes('Impact_Zonal_Coordinator')) {
            setData('zonal_impact_cell_ids', []);
        }
    };

    const needsLeaderCell = data.roles.includes('Impact_Leaders');
    const needsZonalCells = data.roles.includes('Impact_Zonal_Coordinator');
    const toggleZonalCell = (cellId: string) => {
        setData(
            'zonal_impact_cell_ids',
            data.zonal_impact_cell_ids.includes(cellId)
                ? data.zonal_impact_cell_ids.filter((id) => id !== cellId)
                : [...data.zonal_impact_cell_ids, cellId],
        );
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl" dataTestId={dataTestId}>
            <form onSubmit={submit} className="space-y-5 p-6" data-testid="users-add-form">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Add user</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Provision a new account with one or more Spatie roles. They will need to verify their email on first login.
                    </p>
                </div>

                {/* Name */}
                <div className="space-y-1.5">
                    <InputLabel htmlFor="add-user-name" value="Full name" />
                    <TextInput
                        id="add-user-name"
                        name="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="block w-full"
                        autoComplete="name"
                        placeholder="Jane Doe"
                        required
                    />
                    <InputError message={errors.name} />
                </div>

                {/* Email */}
                <div className="space-y-1.5">
                    <InputLabel htmlFor="add-user-email" value="Email address" />
                    <TextInput
                        id="add-user-email"
                        type="email"
                        name="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className="block w-full"
                        autoComplete="email"
                        placeholder="jane.doe@impact.test"
                        required
                    />
                    <InputError message={errors.email} />
                </div>

                {/* Roles — checkbox grid */}
                <div className="space-y-2">
                    <InputLabel value="Roles (at least one)" />
                    <div
                        className="grid grid-cols-1 gap-1.5 sm:grid-cols-2"
                        data-testid="add-user-roles-grid"
                    >
                        {rolesForNew.map((role) => {
                            const checked = data.roles.includes(role);
                            return (
                                <label
                                    key={role}
                                    className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors ${
                                        checked
                                            ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-700/50 dark:bg-indigo-900/30'
                                            : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={() => toggleRole(role)}
                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                        data-testid={`add-user-role-${role}`}
                                    />
                                    <span className="flex-1 truncate text-gray-800 dark:text-gray-200">{role}</span>
                                    {checked && <RoleBadge role={role} />}
                                </label>
                            );
                        })}
                    </div>
                    <InputError message={errors.roles} />
                </div>

                {/* Active role dropdown */}
                <div className="space-y-1.5">
                    <InputLabel htmlFor="add-user-active-role" value="Active role on first login" />
                    <select
                        id="add-user-active-role"
                        name="active_role"
                        value={data.active_role}
                        onChange={(e) => setData('active_role', e.target.value)}
                        className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                        data-testid="add-user-active-role"
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

                {/* Role-specific Impact Cell assignments */}
                {needsLeaderCell && (
                    <div className="space-y-1.5 rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-800/50 dark:bg-indigo-900/20">
                        <InputLabel htmlFor="add-user-impact-cell" value="Impact Cell for this leader" />
                        <select
                            id="add-user-impact-cell"
                            name="impact_cell_id"
                            value={data.impact_cell_id}
                            onChange={(e) => setData('impact_cell_id', e.target.value)}
                            className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                            data-testid="add-user-impact-cell"
                            required
                        >
                            <option value="">— Pick the cell this leader heads —</option>
                            {cellsList.map((cell) => (
                                <option key={cell.id} value={cell.id}>{cell.name}</option>
                            ))}
                        </select>
                        <p className="text-xs text-gray-500 dark:text-gray-400">Required for Impact Leaders. Select one cell.</p>
                        <InputError message={errors.impact_cell_id} />
                    </div>
                )}

                {needsZonalCells && (
                    <div className="space-y-2 rounded-lg border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-800/50 dark:bg-amber-900/20">
                        <InputLabel value="Impact Cells covered by this Zonal Coordinator" />
                        <div className="grid max-h-52 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2" data-testid="add-user-zonal-cells">
                            {cellsList.map((cell) => {
                                const checked = data.zonal_impact_cell_ids.includes(cell.id);
                                return (
                                    <label key={cell.id} className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors ${checked ? 'border-amber-300 bg-amber-100/70 dark:border-amber-700 dark:bg-amber-900/40' : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800'}`}>
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={() => toggleZonalCell(cell.id)}
                                            className="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-900"
                                            data-testid={`add-user-zonal-cell-${cell.id}`}
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

                {/* Password + confirmation */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <InputLabel htmlFor="add-user-password" value="Password" />
                        <TextInput
                            id="add-user-password"
                            type="password"
                            name="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="block w-full"
                            autoComplete="new-password"
                            placeholder="Min 8 characters"
                            required
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="space-y-1.5">
                        <InputLabel htmlFor="add-user-password-confirmation" value="Confirm password" />
                        <TextInput
                            id="add-user-password-confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className="block w-full"
                            autoComplete="new-password"
                            placeholder="Re-enter"
                            required
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>
                </div>

                {/* Footer */}
                <div className="flex items-center justify-end gap-2 pt-2">
                    <SecondaryButton type="button" onClick={onClose} disabled={processing}>
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton
                        type="submit"
                        disabled={processing || data.roles.length === 0 || (needsZonalCells && data.zonal_impact_cell_ids.length === 0) || (needsLeaderCell && !data.impact_cell_id)}
                        data-testid="users-add-submit"
                    >
                        {processing ? 'Creating…' : 'Create user'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
