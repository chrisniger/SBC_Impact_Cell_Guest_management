import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import RoleBadge from '@/Components/RoleBadge';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/**
 * Phase 34 — Admin Roles & Permissions management page.
 *
 * Replaces the Phase 06d.0 "Coming soon" stub with a real editor:
 *
 *   - Role table: name (canonical badge), user group, member count,
 *     assigned permissions (pills), Edit / Delete actions.
 *   - "Add role" modal: name + permission checkbox grid → POST
 *     /admin/roles-permissions.
 *   - Edit modal: permissions grid; the NAME input is disabled for
 *     canonical roles (server-enforced — canonical role names are the
 *     single source of truth the permission matrix keys off).
 *   - Delete is guarded server-side (canonical + roles with members);
 *     the client also disables it for those cases.
 *   - Permission catalog section with an "Add permission" inline form.
 */

interface RoleRow {
    id: number;
    name: string;
    guard_name: string;
    is_canonical: boolean;
    group: string | null;
    member_count: number;
    permissions: string[];
}

interface PageProps {
    roles: RoleRow[];
    permissions: string[];
    canonical: string[];
    flash?: { success?: string; error?: string } | null;
}

const GROUP_LABELS: Record<string, string> = {
    impactCell: 'Impact Cell',
    followUpOfficer: 'Follow-Up Officer',
    followUpTeam: 'Follow-Up Team',
};

const shieldIcon = <><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></>;
const starIcon = <><polygon points="12 2 15 8.5 22 9.3 17 14.2 18.5 21 12 17.8 5.5 21 7 14.2 2 9.3 9 8.5 12 2" /></>;
const plusIcon = <><path d="M12 5v14" /><path d="M5 12h14" /></>;

const groupTone = (group: string | null) =>
    group === 'impactCell'
        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
        : group === 'followUpOfficer'
          ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
          : group === 'followUpTeam'
            ? 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300';

export default function AdminRolesPermissionsIndex() {
    const { props } = usePage<any>();
    const roles: RoleRow[] = props.roles ?? [];
    const permissions: string[] = props.permissions ?? [];
    const canonical: string[] = props.canonical ?? [];
    const flash = props.flash;

    const [addOpen, setAddOpen] = useState(false);
    const [editing, setEditing] = useState<RoleRow | null>(null);
    const [permName, setPermName] = useState('');
    const [addingPerm, setAddingPerm] = useState(false);

    const addPermission = () => {
        if (addingPerm || !permName.trim()) return;
        setAddingPerm(true);
        router.post(
            '/admin/roles-permissions/permissions',
            { name: permName.trim() },
            {
                preserveScroll: true,
                onFinish: () => {
                    setAddingPerm(false);
                    setPermName('');
                },
            },
        );
    };

    return (
        <AdminDashboardLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                            Administrator · Roles & Permissions
                        </p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Roles & Permissions
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Manage the 3 user groups, custom roles, and the permission catalog.
                        </p>
                    </div>
                    <PrimaryButton
                        type="button"
                        onClick={() => setAddOpen(true)}
                        data-testid="roles-add-open"
                        className="inline-flex items-center gap-2"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                            {plusIcon}
                        </svg>
                        Add role
                    </PrimaryButton>
                </div>
            }
        >
            <Head title="Roles & Permissions · Admin" />

            {flash?.success && (
                <div className="mb-4 flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800/40 dark:bg-green-900/30 dark:text-green-200" role="status" data-testid="roles-flash-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                        <path d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{flash.success}</span>
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800/40 dark:bg-red-900/30 dark:text-red-200" role="alert" data-testid="roles-flash-error">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <span>{flash.error}</span>
                </div>
            )}

            <div className="space-y-6">
                {/* ─────────── Role table ─────────── */}
                <section
                    className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800"
                    data-testid="roles-table-card"
                >
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-testid="roles-table">
                            <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Role</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Group</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Members</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Permissions</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {roles.map((role) => (
                                    <tr key={role.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40" data-testid={`role-row-${role.name}`}>
                                        <td className="px-4 py-3 text-sm">
                                            <div className="flex items-center gap-2">
                                                <span className={`inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${role.is_canonical ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'}`}>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                                        {role.is_canonical ? starIcon : shieldIcon}
                                                    </svg>
                                                </span>
                                                <span className="font-medium text-gray-900 dark:text-gray-100">{role.name}</span>
                                                {role.is_canonical && (
                                                    <span
                                                        className="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                                                        title="Built-in role — the permission matrix depends on it. Name and deletion are locked."
                                                        data-testid={`role-${role.name}-canonical-badge`}
                                                    >
                                                        system
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${groupTone(role.group)}`}>
                                                {role.group ? (GROUP_LABELS[role.group] ?? role.group) : 'System'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                            <span className="font-mono text-xs">{role.member_count}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {role.permissions.length === 0 ? (
                                                <span className="text-xs text-gray-400 dark:text-gray-500">—</span>
                                            ) : (
                                                <div className="flex max-w-[420px] flex-wrap gap-1">
                                                    {role.permissions.map((p) => (
                                                        <span
                                                            key={p}
                                                            className="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                                            data-testid={`role-${role.name}-perm-${p}`}
                                                        >
                                                            {p}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => setEditing(role)}
                                                    className="inline-flex items-center gap-1 rounded-md border border-indigo-200 bg-white px-2.5 py-1.5 text-xs font-medium text-indigo-700 transition-colors hover:border-indigo-400 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-indigo-700/50 dark:bg-gray-800 dark:text-indigo-300 dark:hover:bg-indigo-900/40"
                                                    data-testid={`role-${role.name}-edit`}
                                                >
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                    </svg>
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (confirm(`Delete role "${role.name}"?`)) {
                                                            router.delete(
                                                                `/admin/roles-permissions/${role.id}`,
                                                                { preserveScroll: true },
                                                            );
                                                        }
                                                    }}
                                                    disabled={role.is_canonical || role.member_count > 0}
                                                    title={
                                                        role.is_canonical
                                                            ? 'System roles cannot be deleted'
                                                            : role.member_count > 0
                                                              ? 'Reassign members before deleting'
                                                              : 'Delete role'
                                                    }
                                                    className="inline-flex items-center gap-1.5 rounded-md border border-red-200 bg-white px-2.5 py-1.5 text-xs font-medium text-red-700 transition-colors hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500/40 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-800/50 dark:bg-gray-800 dark:text-red-300 dark:hover:bg-red-900/30"
                                                    data-testid={`role-${role.name}-delete`}
                                                >
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                                        <path d="M3 6h18" />
                                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                        <path d="M10 11v6" />
                                                        <path d="M14 11v6" />
                                                    </svg>
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {/* ─────────── Permission catalog ─────────── */}
                <section
                    className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800"
                    data-testid="permissions-catalog-card"
                >
                    <header className="flex flex-wrap items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                {shieldIcon}
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                            Permission catalog
                        </h3>
                        <span className="ml-auto text-xs text-gray-500 dark:text-gray-400">{permissions.length} permissions</span>
                    </header>
                    <div className="space-y-4 p-5">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="min-w-[16rem] flex-1">
                                <InputLabel htmlFor="new-permission" value="New permission name" />
                                <TextInput
                                    id="new-permission"
                                    value={permName}
                                    onChange={(e) => setPermName(e.target.value)}
                                    className="mt-1 block w-full"
                                    placeholder="e.g. reports.export"
                                    data-testid="permission-name-input"
                                />
                            </div>
                            <SecondaryButton
                                type="button"
                                onClick={addPermission}
                                disabled={addingPerm || !permName.trim()}
                                data-testid="permission-add-submit"
                            >
                                {addingPerm ? 'Adding…' : 'Add permission'}
                            </SecondaryButton>
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            {permissions.map((p) => (
                                <span
                                    key={p}
                                    className="rounded-md bg-gray-100 px-2 py-1 font-mono text-[11px] text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    data-testid={`permission-pill-${p}`}
                                >
                                    {p}
                                </span>
                            ))}
                            {permissions.length === 0 && (
                                <p className="text-sm text-gray-500 dark:text-gray-400">No permissions defined yet.</p>
                            )}
                        </div>
                    </div>
                </section>
            </div>

            <AddRoleModal show={addOpen} onClose={() => setAddOpen(false)} permissions={permissions} canonical={canonical} dataTestId="roles-add-modal" />
            <EditRoleModal role={editing} onClose={() => setEditing(null)} permissions={permissions} dataTestId="roles-edit-modal" />
        </AdminDashboardLayout>
    );
}

/**
 * Add-role modal: name + permission checkbox grid.
 */
function AddRoleModal({
    show,
    onClose,
    permissions,
    canonical,
    dataTestId,
}: {
    show: boolean;
    onClose: () => void;
    permissions: string[];
    canonical: string[];
    dataTestId?: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        permissions: [] as string[],
    });

    const toggle = (p: string) =>
        setData(
            'permissions',
            data.permissions.includes(p)
                ? data.permissions.filter((x) => x !== p)
                : [...data.permissions, p],
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/admin/roles-permissions', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl" dataTestId={dataTestId}>
            <form onSubmit={submit} className="space-y-5 p-6" data-testid="roles-add-form">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Add role</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Create a custom role. Pick a unique name and attach permissions from the catalog.
                    </p>
                </div>

                <div className="space-y-1.5">
                    <InputLabel htmlFor="add-role-name" value="Role name" />
                    <TextInput
                        id="add-role-name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="block w-full"
                        placeholder="e.g. Regional_Reporter"
                        required
                        data-testid="add-role-name"
                    />
                    <InputError message={errors.name} />
                </div>

                <PermissionCheckboxGrid permissions={permissions} selected={data.permissions} onToggle={toggle} error={errors.permissions} testPrefix="add-role" />

                <div className="flex items-center justify-end gap-2 pt-2">
                    <SecondaryButton type="button" onClick={onClose} disabled={processing}>Cancel</SecondaryButton>
                    <PrimaryButton type="submit" disabled={processing || !data.name.trim()} data-testid="roles-add-submit">
                        {processing ? 'Creating…' : 'Create role'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}

/**
 * Edit-role modal: permissions grid; name locked for canonical roles.
 */
function EditRoleModal({
    role,
    onClose,
    permissions,
    dataTestId,
}: {
    role: RoleRow | null;
    onClose: () => void;
    permissions: string[];
    dataTestId?: string;
}) {
    const { data, setData, put, processing, errors } = useForm({
        name: role?.name ?? '',
        permissions: role?.permissions ?? ([] as string[]),
    });

    // Re-sync form state whenever a different role is opened.
    const [lastId, setLastId] = useState<number | null>(null);
    if (role && role.id !== lastId) {
        setLastId(role.id);
        setData({ name: role.name, permissions: role.permissions });
    }

    const toggle = (p: string) =>
        setData(
            'permissions',
            data.permissions.includes(p)
                ? data.permissions.filter((x) => x !== p)
                : [...data.permissions, p],
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        if (!role) return;
        put(`/admin/roles-permissions/${role.id}`, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    if (!role) return null;

    return (
        <Modal show={Boolean(role)} onClose={onClose} maxWidth="2xl" dataTestId={dataTestId}>
            <form onSubmit={submit} className="space-y-5 p-6" data-testid="roles-edit-form">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Edit role</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {role.is_canonical
                            ? 'System role — the name is locked because the permission matrix depends on it. Only permissions can change.'
                            : 'Rename the role and/or update its permissions.'}
                    </p>
                </div>

                <div className="space-y-1.5">
                    <InputLabel htmlFor="edit-role-name" value="Role name" />
                    <TextInput
                        id="edit-role-name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="block w-full"
                        disabled={role.is_canonical}
                        required
                        data-testid="edit-role-name"
                    />
                    <InputError message={errors.name} />
                </div>

                <PermissionCheckboxGrid permissions={permissions} selected={data.permissions} onToggle={toggle} error={errors.permissions} testPrefix="edit-role" />

                <div className="flex items-center justify-end gap-2 pt-2">
                    <SecondaryButton type="button" onClick={onClose} disabled={processing}>Cancel</SecondaryButton>
                    <PrimaryButton type="submit" disabled={processing} data-testid="roles-edit-submit">
                        {processing ? 'Saving…' : 'Save changes'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}

/**
 * Shared permission checkbox grid (add + edit modals).
 */
function PermissionCheckboxGrid({
    permissions,
    selected,
    onToggle,
    error,
    testPrefix,
}: {
    permissions: string[];
    selected: string[];
    onToggle: (p: string) => void;
    error?: string;
    testPrefix: string;
}) {
    return (
        <div className="space-y-2">
            <InputLabel value="Permissions" />
            {permissions.length === 0 ? (
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    No permissions in the catalog yet — add one below the table first.
                </p>
            ) : (
                <div className="grid max-h-60 grid-cols-1 gap-1.5 overflow-y-auto sm:grid-cols-2" data-testid={`${testPrefix}-permissions-grid`}>
                    {permissions.map((p) => {
                        const checked = selected.includes(p);
                        return (
                            <label
                                key={p}
                                className={`flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors ${
                                    checked
                                        ? 'border-indigo-300 bg-indigo-50 dark:border-indigo-700/50 dark:bg-indigo-900/30'
                                        : 'border-gray-200 bg-white hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-gray-600'
                                }`}
                            >
                                <input
                                    type="checkbox"
                                    checked={checked}
                                    onChange={() => onToggle(p)}
                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                    data-testid={`${testPrefix}-perm-${p}`}
                                />
                                <span className="flex-1 truncate font-mono text-xs text-gray-800 dark:text-gray-200">{p}</span>
                            </label>
                        );
                    })}
                </div>
            )}
            {error && <InputError message={error} />}
        </div>
    );
}
