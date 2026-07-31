import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import StatusPill from '@/Components/StatusPill';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface ImpactCellDetail {
    id: string;
    name: string;
    phone: string | null;
    address: string | null;
    is_primary: boolean;
    parent_cell_id: string | null;
    parent?: { id: string; name: string } | null;
    sub_cells?: { id: string; name: string; is_primary: boolean }[];
    submission_count?: number;
    member_count?: number;
    // Phase 13 — leadership team. All nullable; Admin can fill any subset.
    leader_name: string | null;
    leader_phone: string | null;
    assistant_name: string | null;
    assistant_phone: string | null;
    welfare_officer_name: string | null;
    welfare_officer_phone: string | null;
    // Phase 13 — the user(s) whose `users.impact_cell_id` = this cell.
    // Convention: most often a single Impact_Leaders. Render the count so
    // admins immediately see "this cell has 2 leaders" without a follow-up
    // fetch — the ImpactCellController::show() controller eager-loads this
    // to avoid the N+1 trap.
    leader_users?: { id: string; name: string; email: string }[];
}

/**
 * Phase 13 — /impact-cells/{id} Inertia page.
 *
 * Compile-time inputs (from `ImpactCellController::show()`):
 *   - `cell`: ImpactCellDetail (with `parent`, `subCells`, `leader_users`
 *     eager-loaded; Phase 13 added the 6 leadership-team columns).
 *   - `activeRole`: global-shared admin actor's active role (used to gate
 *     the admin-only Edit toggle — admin / Impact_Cell_Admin both qualify).
 *
 * UX
 * --
 * Read-only display by default. Admin (Administrator OR Impact_Cell_Admin)
 * sees an "Edit leadership team" button that toggles an inline form
 * containing the 6 text fields. The form submits via
 * `router.put(/impact-cells/{id}, …)` reusing the existing
 * `ImpactCellController::update` route — no new endpoint needed.
 *
 * Auth model
 * ----------
 * Admin-only because:
 *   - ImpactCellPolicy::update() gates `Administrator OR Impact_Cell_Admin`.
 *   - We pass the actor's `activeRole` global-share through, compare
 *     against the policy's gates, and only render the toggle if true.
 * (If the page is visited by a non-admin, the toggle won't even render.)
 */
export default function ImpactCellsShow({ cell, activeRole }: { cell: ImpactCellDetail; activeRole: string | null; }) {
    const subCells = cell.sub_cells ?? [];
    const leaderUsers = cell.leader_users ?? [];

    const cellsIconPath = <><path d="M3 3h18v18H3z" /><path d="M3 9h18M9 21V9" /></>;
    const layersIconPath = <><polygon points="12 2 2 7 12 12 22 7 12 2" /><polyline points="2 17 12 22 22 17" /><polyline points="2 12 12 17 22 12" /></>;
    const arrowRightIcon = <><path d="M5 12h14" /><path d="m12 5 7 7-7 7" /></>;
    const emptyIconPath = <><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></>;
    const editIcon = <><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></>;
    const usersIcon = <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></>;
    const leadershipIcon = <><path d="M12 2l3 6 6 1-4.5 4 1 6L12 16l-5.5 3 1-6L3 9l6-1z" /></>;

    const isAdmin = activeRole === 'Administrator' || activeRole === 'Impact_Cell_Admin';

    const [isEditing, setIsEditing] = useState(false);
    const editForm = useForm<ImpactCellEditPayload>({
        name: cell.name,
        phone: cell.phone ?? '',
        address: cell.address ?? '',
        parent_cell_id: cell.parent_cell_id ?? '',
        is_primary: cell.is_primary,
        order: 0,
        leader_name: cell.leader_name ?? '',
        leader_phone: cell.leader_phone ?? '',
        assistant_name: cell.assistant_name ?? '',
        assistant_phone: cell.assistant_phone ?? '',
        welfare_officer_name: cell.welfare_officer_name ?? '',
        welfare_officer_phone: cell.welfare_officer_phone ?? '',
    });

    const startEditing = () => {
        editForm.setData(cellToEditPayload(cell));
        editForm.clearErrors();
        setIsEditing(true);
    };

    const cancelEditing = () => {
        setIsEditing(false);
        editForm.clearErrors();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        editForm.put(`/impact-cells/${cell.id}`, {
            preserveScroll: true,
            onSuccess: () => setIsEditing(false),
        });
    };

    return (
        <AdminDashboardLayout
            header={
                <div>
                    <nav className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <Link href={route('impact-cells.index')} className="transition-colors hover:text-indigo-600 dark:hover:text-indigo-400">
                            Impact Cells
                        </Link>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                            {arrowRightIcon}
                        </svg>
                        <span className="text-gray-700 dark:text-gray-300">{cell.name}</span>
                    </nav>
                    <div className="mt-2 flex items-center gap-3">
                        <h2 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{cell.name}</h2>
                        {cell.is_primary && <StatusPill tone="brand" dot>Primary</StatusPill>}
                        {!cell.is_primary && <StatusPill tone="info" dot>Sub-cell</StatusPill>}
                    </div>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Active role: <span className="font-mono">{activeRole ?? '—'}</span>
                    </p>
                </div>
            }
        >
            <Head title={cell.name} />

            <div className="space-y-6">
                {/* Hero band */}
                <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-blue-50 p-6 shadow-card dark:border-indigo-900/40 dark:from-indigo-950/40 dark:via-gray-900 dark:to-blue-950/40">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="flex items-center gap-4">
                            <span className="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6" aria-hidden="true">
                                    {cellsIconPath}
                                </svg>
                            </span>
                            <div>
                                <h1 className="text-xl font-bold text-gray-900 dark:text-white">{cell.name}</h1>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    {cell.is_primary ? 'Primary cell — anchor of outreach' : 'Sub-cell reporting to a primary'}
                                </p>
                            </div>
                        </div>

                        {/* Phase 13 — Admin-only Edit toggle */}
                        {isAdmin && ! isEditing && (
                            <button
                                type="button"
                                onClick={startEditing}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-500/50 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300"
                                data-testid="impact-cell-edit-button"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                    {editIcon}
                                </svg>
                                Edit leadership team
                            </button>
                        )}
                    </div>
                </section>

                {/* Phase 13 — Edit form (only when admin toggled editing) */}
                {isAdmin && isEditing && (
                    <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-indigo-200 bg-white shadow-card dark:border-indigo-700/40 dark:bg-gray-800" data-testid="impact-cell-edit-form-card">
                        <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    {leadershipIcon}
                                </svg>
                            </span>
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                                Edit Leadership Team
                            </h3>
                        </header>
                        <form onSubmit={submit} className="px-5 py-5">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <TextFieldRow label="Leader name" id="leader_name" form={editForm} />
                                <TextFieldRow label="Leader phone" id="leader_phone" form={editForm} />
                                <TextFieldRow label="Assistant name" id="assistant_name" form={editForm} />
                                <TextFieldRow label="Assistant phone" id="assistant_phone" form={editForm} />
                                <TextFieldRow label="Welfare officer name" id="welfare_officer_name" form={editForm} />
                                <TextFieldRow label="Welfare officer phone" id="welfare_officer_phone" form={editForm} />
                            </div>
                            <div className="mt-5 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={cancelEditing}
                                    className="inline-flex items-center rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={editForm.processing}
                                    className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:cursor-not-allowed disabled:opacity-60"
                                    data-testid="impact-cell-edit-submit"
                                >
                                    {editForm.processing ? 'Saving…' : 'Save leadership team'}
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800">
                        <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                </svg>
                            </span>
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Details</h3>
                        </header>
                        <dl className="px-5 py-4 text-sm">
                            <div className="flex items-baseline justify-between border-b border-gray-100 py-2 dark:border-gray-700">
                                <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Phone</dt>
                                <dd className="font-mono text-gray-900 dark:text-gray-100">{cell.phone ?? '—'}</dd>
                            </div>
                            <div className="flex items-baseline justify-between border-b border-gray-100 py-2 dark:border-gray-700">
                                <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Address</dt>
                                <dd className="text-gray-900 dark:text-gray-100">{cell.address ?? '—'}</dd>
                            </div>
                            <div className="flex items-baseline justify-between py-2">
                                <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Parent</dt>
                                <dd>
                                    {cell.parent ? (
                                        <Link href={route('impact-cells.show', cell.parent.id)} className="text-indigo-600 hover:underline dark:text-indigo-400">
                                            {cell.parent.name}
                                        </Link>
                                    ) : <span className="text-gray-400 dark:text-gray-500">—</span>}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    {/* Phase 13 — Leadership Team card (read-only display) */}
                    <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="impact-cell-leadership-card">
                        <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    {leadershipIcon}
                                </svg>
                            </span>
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Leadership Team</h3>
                            <span className="ml-auto text-xs text-gray-500 dark:text-gray-400 tabular-nums">{leaderUsers.length} assigned</span>
                        </header>
                        <dl className="px-5 py-4 text-sm">
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <LeadershipPair label="Leader"           name={cell.leader_name}           phone={cell.leader_phone} />
                                <LeadershipPair label="Assistant"        name={cell.assistant_name}        phone={cell.assistant_phone} />
                                <LeadershipPair label="Welfare Officer"  name={cell.welfare_officer_name}  phone={cell.welfare_officer_phone} />
                            </div>
                            {leaderUsers.length > 0 && (
                                <div className="mt-4 border-t border-gray-100 pt-3 dark:border-gray-700">
                                    <p className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Assigned users</p>
                                    <ul className="mt-2 space-y-1">
                                        {leaderUsers.map((u) => (
                                            <li key={u.id} className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300" data-testid={`leader-user-${u.id}`}>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5 text-indigo-500" aria-hidden="true">
                                                    {usersIcon}
                                                </svg>
                                                <span className="font-medium text-gray-900 dark:text-gray-100">{u.name}</span>
                                                <span className="font-mono text-xs text-gray-500 dark:text-gray-400">({u.email})</span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </dl>
                    </section>

                    <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="sub-cells-card">
                        <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    {layersIconPath}
                                </svg>
                            </span>
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Sub-cells</h3>
                            <span className="ml-auto text-xs text-gray-500 dark:text-gray-400">{subCells.length}</span>
                        </header>
                        <div className="px-5 py-4">
                            {subCells.length === 0 ? (
                                <EmptyState
                                    title="No sub-cells"
                                    description="This cell has no sub-cells assigned to it."
                                    iconPath={emptyIconPath}
                                />
                            ) : (
                                <ul className="space-y-2">
                                    {subCells.map((s) => (
                                        <li key={s.id}>
                                            <Link
                                                href={route('impact-cells.show', s.id)}
                                                className="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-2.5 text-sm transition-all hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20"
                                                data-testid={`sub-cell-${s.id}`}
                                            >
                                                <span className="font-medium text-gray-900 dark:text-gray-100">{s.name}</span>
                                                {s.is_primary && <StatusPill tone="brand" dot>Primary</StatusPill>}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminDashboardLayout>
    );
}

/**
 * Inline read-only cell for one leadership row. Renders "—" placeholders
 * when either name or phone is blank so the surface stays tidy.
 */
function LeadershipPair({ label, name, phone }: { label: 'Leader' | 'Assistant' | 'Welfare Officer'; name: string | null; phone: string | null; }) {
    const hasContent = (name ?? '') !== '' || (phone ?? '') !== '';
    return (
        <div className="rounded-lg border border-gray-100 bg-gray-50/40 p-3 dark:border-gray-700/60 dark:bg-gray-900/30">
            <dt className="text-[11px] font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{label}</dt>
            {! hasContent ? (
                <dd className="mt-1 text-sm text-gray-400 dark:text-gray-500">—</dd>
            ) : (
                <dd className="mt-1">
                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{name ?? '—'}</p>
                    <p className="mt-0.5 font-mono text-xs text-gray-600 dark:text-gray-400">{phone ?? '—'}</p>
                </dd>
            )}
        </div>
    );
}

/**
 * Phase 13 — typed edit-payload shape that mirrors exactly the keys
 * `ImpactCellController::validateCell()` accepts on the existing PUT
 * route. Defining it here lets `useForm<ImpactCellEditPayload>(...)`
 * keep the form typed end-to-end (no `as Record<string, unknown>`
 * casts inside `TextFieldRow`). Server-side validation still rejects
 * bad input; client-side types catch missing-field hand-rolls at
 * compile time.
 */
interface ImpactCellEditPayload {
    name: string;
    phone: string;
    address: string;
    parent_cell_id: string;
    is_primary: boolean;
    order: number;
    leader_name: string;
    leader_phone: string;
    assistant_name: string;
    assistant_phone: string;
    welfare_officer_name: string;
    welfare_officer_phone: string;
}

/**
 * Sync a cell's stored fields to the editable shape (treats nulls as
 * '' so Inertia's useForm doesn't crash on strict types).
 */
function cellToEditPayload(cell: ImpactCellDetail): ImpactCellEditPayload {
    return {
        name: cell.name,
        phone: cell.phone ?? '',
        address: cell.address ?? '',
        parent_cell_id: cell.parent_cell_id ?? '',
        is_primary: cell.is_primary,
        order: 0,
        leader_name: cell.leader_name ?? '',
        leader_phone: cell.leader_phone ?? '',
        assistant_name: cell.assistant_name ?? '',
        assistant_phone: cell.assistant_phone ?? '',
        welfare_officer_name: cell.welfare_officer_name ?? '',
        welfare_officer_phone: cell.welfare_officer_phone ?? '',
    };
}

/**
 * Inline editable text-input row bound to the page's editForm (useForm).
 * Shared between the 6 Leadership-team fields in the admin edit form.
 * Generic on `K extends EditableField` so `id` is type-safe and the
 * `setData` call has no casts.
 */
type EditableField = 'leader_name' | 'leader_phone' | 'assistant_name' | 'assistant_phone' | 'welfare_officer_name' | 'welfare_officer_phone';

/**
 * Inline editable text-input row bound to the page's editForm (useForm).
 * Shared between the 6 Leadership-team fields in the admin edit form.
 *
 * No generic on `id` — Inertia's `setData` second parameter is typed as
 * `FormDataValues<T, K extends keyof T>`, which only resolves cleanly to
 * `{string}` when `K` is a concrete union (e.g. `EditableField`); when
 * `K` is still bound to a generic `<K extends EditableField>`, the lookup
 * collapses and `e.target.value` (raw `string`) no longer satisfies the
 * inferred param type. Using `EditableField` directly keeps the call
 * site type-safe without a cast.
 */
function TextFieldRow({ label, id, form }: {
    label: string;
    id: EditableField;
    form: ReturnType<typeof useForm<ImpactCellEditPayload>>;
}) {
    const value = form.data[id];
    const error = form.errors[id];
    return (
        <div className="space-y-1.5">
            <InputLabel htmlFor={id} value={label} />
            <input
                id={id}
                name={id}
                type="text"
                value={value}
                onChange={(e) => form.setData(id, e.target.value)}
                className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                data-testid={`impact-cell-edit-${id}`}
                maxLength={255}
            />
            <InputError message={error} />
        </div>
    );
}
