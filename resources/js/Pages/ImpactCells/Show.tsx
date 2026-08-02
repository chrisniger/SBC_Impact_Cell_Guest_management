import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import EmptyState from '@/Components/EmptyState';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import ReadOnlyBanner from '@/Components/ReadOnlyBanner';
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
    order: number;
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

interface PrimaryCellRef {
    id: string;
    name: string;
}

/**
 * Phase 13 + Phase 17 — /impact-cells/{id} Inertia page.
 *
 * Compile-time inputs (from `ImpactCellController::show()`):
 *   - `cell`:             ImpactCellDetail (with `parent`, `subCells`,
 *                         `leader_users` eager-loaded; Phase 13 added the
 *                         6 leadership-team columns).
 *   - `attachablePrims`:  PrimaryCellRef[] (cells eligible for the
 *                         Sub-cells editor card to re-parent under THIS
 *                         primary — server-pre-filtered to drop self +
 *                         any primary that has its own sub-cells).
 *   - `activeRole`:       global-shared admin actor's active role.
 *
 * UX
 * --
 * Read-only display by default. Administrator sees THREE independent
 * "Edit" toggles, one per card (Phase 35 — Impact_Cell_Admin is
 * read-only on this surface; the toggles hide via the server-computed
 * canEdit* props below):
 *
 *   1. **Details**        → toggle reveals an inline form for name, phone,
 *                            address, is_primary, parent_cell_id, order.
 *   2. **Leadership Team** → (Phase 13) toggle reveals the 6 free-text
 *                            leadership fields.
 *   3. **Sub-cells**      → (Phase 17) ONLY when THIS cell is primary:
 *                            toggle reveals the sub-cell attach picker +
 *                            per-child promote actions.
 *
 * Each editor has its own `useState` boolean (independent toggles) and
 * its own `useForm` instance, so cancelling one editor never trashes
 * another's in-flight changes.
 *
 * Auth model
 * ----------
 * Writes are Administrator-only (Phase 35 — Impact_Cell_Admin read-only),
 * except assigned Impact_Leaders may edit their OWN cell's leadership team:
 *   - ImpactCellPolicy::create/update/delete gate reads/writes.
 *   - The server revalidates every action and is the source of truth.
 */
export default function ImpactCellsShow({ cell, activeRole, attachablePrims = [], canEditDetails = false, canEditLeadership = false }: { cell: ImpactCellDetail; activeRole: string | null; attachablePrims?: PrimaryCellRef[]; canEditDetails?: boolean; canEditLeadership?: boolean; }) {
    const subCells = cell.sub_cells ?? [];
    const leaderUsers = cell.leader_users ?? [];

    const cellsIconPath = <><path d="M3 3h18v18H3z" /><path d="M3 9h18M9 21V9" /></>;
    const layersIconPath = <><polygon points="12 2 2 7 12 12 22 7 12 2" /><polyline points="2 17 12 22 22 17" /><polyline points="2 12 12 17 22 12" /></>;
    const arrowRightIcon = <><path d="M5 12h14" /><path d="m12 5 7 7-7 7" /></>;
    const emptyIconPath = <><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></>;
    const editIcon = <><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></>;
    const usersIcon = <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></>;
    const leadershipIcon = <><path d="M12 2l3 6 6 1-4.5 4 1 6L12 16l-5.5 3 1-6L3 9l6-1z" /></>;
    const fileIcon = <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /></>;

    // Phase 32 + Phase 35 — server-computed edit gates (ImpactCellPolicy).
    // `canEditDetails`  = update() (Administrator only) — owns name + hierarchy.
    // `canEditLeadership` = updateLeadership() (Administrator on any cell,
    // or assigned Impact_Leaders on their OWN cell). Leaders therefore see
    // ONLY the leadership editor — never the cell-name details editor.
    // Phase 35: Impact_Cell_Admin gets neither (read-only surface).
    const canEditAnything = canEditDetails || canEditLeadership;

    // ── THREE independent editor toggles. ─────────────────────────────
    const [isEditingDetails, setIsEditingDetails] = useState(false);
    const [isEditingLeadership, setIsEditingLeadership] = useState(false);
    const [isManagingSubCells, setIsManagingSubCells] = useState(false);

    // ── THREE independent useForm instances. ──────────────────────────
    const leadershipForm = useForm<ImpactCellLeadershipPayload>({
        leader_name: cell.leader_name ?? '',
        leader_phone: cell.leader_phone ?? '',
        assistant_name: cell.assistant_name ?? '',
        assistant_phone: cell.assistant_phone ?? '',
        welfare_officer_name: cell.welfare_officer_name ?? '',
        welfare_officer_phone: cell.welfare_officer_phone ?? '',
    });

    const detailsForm = useForm<ImpactCellDetailsPayload>({
        name: cell.name,
        phone: cell.phone ?? '',
        address: cell.address ?? '',
        parent_cell_id: cell.parent_cell_id ?? '',
        is_primary: cell.is_primary,
        order: cell.order ?? 0,
    });

    const subCellsForm = useForm<{ child_id: string }>({
        child_id: '',
    });

    // ── Lead-Leadership handlers. ─────────────────────────────────────
    const startEditingLeadership = () => {
        leadershipForm.setData(cellToLeadershipPayload(cell));
        leadershipForm.clearErrors();
        setIsEditingLeadership(true);
    };
    const cancelEditingLeadership = () => {
        setIsEditingLeadership(false);
        leadershipForm.clearErrors();
    };
    const submitLeadership = (e: React.FormEvent) => {
        e.preventDefault();
        // Phase 32 — dedicated leadership-only endpoint so the 6 free-text
        // fields save WITHOUT tripping validateCell()'s required name/
        // is_primary rules (the old shared PUT silently swallowed saves).
        leadershipForm.put(`/impact-cells/${cell.id}/leadership`, {
            preserveScroll: true,
            onSuccess: () => setIsEditingLeadership(false),
        });
    };

    // ── Phase 17 — Details handlers. ──────────────────────────────────
    const startEditingDetails = () => {
        detailsForm.setData(cellToDetailsPayload(cell));
        detailsForm.clearErrors();
        setIsEditingDetails(true);
    };
    const cancelEditingDetails = () => {
        setIsEditingDetails(false);
        detailsForm.clearErrors();
    };
    const submitDetails = (e: React.FormEvent) => {
        e.preventDefault();
        detailsForm.put(`/impact-cells/${cell.id}`, {
            preserveScroll: true,
            onSuccess: () => setIsEditingDetails(false),
            // Defensive: even with no validity, force boolean coercion so
            // a box-unchecked submission posts `is_primary=true` cleanly.
        });
    };

    // ── Phase 17 — Sub-cells handlers. ─────────────────────────────────
    const startManagingSubCells = () => {
        subCellsForm.setData({ child_id: '' });
        subCellsForm.clearErrors();
        setIsManagingSubCells(true);
    };
    const cancelManagingSubCells = () => {
        setIsManagingSubCells(false);
        subCellsForm.clearErrors();
    };
    const attachSelectedCell = (e: React.FormEvent) => {
        e.preventDefault();
        if (! subCellsForm.data.child_id) return;
        subCellsForm.post(`/impact-cells/${cell.id}/attach-sub-cell`, {
            preserveScroll: true,
            onSuccess: () => subCellsForm.setData({ child_id: '' }),
        });
    };
    // Per Phase 17 "fast-action, no modal" — server-side validation is the
    // sole guard. If admin clicks accidentally, re-attaching back is a
    // single subsequent click (atomic POST + same-page reload).
    const promoteToPrimary = (childId: string, childName: string) => {
        router.post(`/impact-cells/${cell.id}/detach-sub-cell`, { child_id: childId }, {
            preserveScroll: true,
            headers: { 'X-Back-Action': 'detach-sub-cell' },
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
                {/* Phase 35 — read-only notice for Impact_Cell_Admin (view-only surface). */}
                {activeRole === 'Impact_Cell_Admin' && (
                    <ReadOnlyBanner
                        testId="impact-cell-detail-readonly-banner"
                        description="Impact_Cell_Admin can view this cell but cannot edit its details, leadership team, or sub-cells. Only an Administrator can make changes."
                    />
                )}

                {/* Phase 36 — zonal coordinators: view-only on assigned cells. */}
                {activeRole === 'Impact_Zonal_Coordinator' && (
                    <ReadOnlyBanner
                        testId="impact-cell-detail-zonal-readonly-banner"
                        description="Zonal Coordinators can view this cell's activity but cannot edit its details, leadership team, or sub-cells."
                    />
                )}

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

                        {/* Phase 17 + Phase 32 — edit toggles, ALL hidden when no editor is engaged. */}
                        {canEditAnything && ! isEditingDetails && ! isEditingLeadership && ! isManagingSubCells && (
                            <div className="flex flex-wrap items-center gap-2">
                                {canEditDetails && (
                                    <button
                                        type="button"
                                        onClick={startEditingDetails}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-500/50 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300"
                                        data-testid="impact-cell-edit-details-button"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                            {fileIcon}
                                        </svg>
                                        Edit details
                                    </button>
                                )}
                                {canEditLeadership && (
                                    <button
                                        type="button"
                                        onClick={startEditingLeadership}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-500/50 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300"
                                        data-testid="impact-cell-edit-button"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                            {editIcon}
                                        </svg>
                                        Edit leadership team
                                    </button>
                                )}
                                {canEditDetails && cell.is_primary && (
                                    <button
                                        type="button"
                                        onClick={startManagingSubCells}
                                        data-testid="impact-cell-manage-subcells-button"
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-500/50 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300"
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                            {layersIconPath}
                                        </svg>
                                        Manage sub-cells
                                    </button>
                                )}
                            </div>
                        )}
                    </div>
                </section>

                {/* Phase 17 — Details editor (independent useForm, separate toggle). */}
                {canEditDetails && isEditingDetails && (
                    <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-indigo-200 bg-white shadow-card dark:border-indigo-700/40 dark:bg-gray-800" data-testid="impact-cell-edit-details-form-card">
                        <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    {fileIcon}
                                </svg>
                            </span>
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                                Edit Details
                            </h3>
                        </header>
                        <form onSubmit={submitDetails} className="space-y-4 px-5 py-5">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <DetailsField label="Cell name" id="name" form={detailsForm} required maxLength={255} />
                                <DetailsField label="Order" id="order" type="number" form={detailsForm} />
                                <DetailsField label="Phone" id="phone" form={detailsForm} maxLength={32} />
                                <DetailsField label="Address" id="address" form={detailsForm} maxLength={255} />
                            </div>
                            <div className="space-y-2 rounded-md border border-gray-100 bg-gray-50/40 px-3 py-3 dark:border-gray-700/60 dark:bg-gray-900/30" data-testid="impact-cell-edit-details-cell-type">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <InputLabel htmlFor="is_primary" value="Cell type" />
                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Primary cells are root anchors. Sub-cells report to a primary cell (their parent).
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusPill tone={detailsForm.data.is_primary ? 'brand' : 'info'} dot>
                                            {detailsForm.data.is_primary ? 'Primary' : 'Sub-cell'}
                                        </StatusPill>
                                        <label className="inline-flex cursor-pointer items-center gap-2">
                                            <input
                                                id="is_primary"
                                                type="checkbox"
                                                checked={detailsForm.data.is_primary}
                                                onChange={(e) => {
                                                    detailsForm.setData('is_primary', e.target.checked);
                                                    if (e.target.checked) detailsForm.setData('parent_cell_id', '');
                                                }}
                                                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800"
                                                data-testid="impact-cell-edit-details-is-primary-toggle"
                                            />
                                            <span className="text-xs font-medium text-gray-700 dark:text-gray-300">{detailsForm.data.is_primary ? 'Primary' : 'Sub-cell'}</span>
                                        </label>
                                    </div>
                                </div>
                                {! detailsForm.data.is_primary && (
                                    <div className="mt-3 space-y-1.5">
                                        <InputLabel htmlFor="parent_cell_id" value="Parent primary cell" />
                                        <select
                                            id="parent_cell_id"
                                            value={detailsForm.data.parent_cell_id}
                                            onChange={(e) => detailsForm.setData('parent_cell_id', e.target.value)}
                                            className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                            data-testid="impact-cell-edit-details-parent-select"
                                            required
                                        >
                                            <option value="">— Select parent —</option>
                                            {attachablePrims.map((p) => (
                                                <option key={p.id} value={p.id}>{p.name}</option>
                                            ))}
                                        </select>
                                        <InputError message={detailsForm.errors.parent_cell_id} />
                                        {attachablePrims.length === 0 && (
                                            <p className="text-xs text-amber-600 dark:text-amber-300">
                                                No other primary cells available. Create another primary first, or revert cell to primary.
                                            </p>
                                        )}
                                    </div>
                                )}
                                <InputError message={detailsForm.errors.is_primary} />
                            </div>
                            <div className="mt-2 flex items-center justify-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                                <button
                                    type="button"
                                    onClick={cancelEditingDetails}
                                    className="inline-flex items-center rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={detailsForm.processing}
                                    className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:cursor-not-allowed disabled:opacity-60"
                                    data-testid="impact-cell-edit-details-submit"
                                >
                                    {detailsForm.processing ? 'Saving…' : 'Save details'}
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                {/* Phase 13 — Leadership Team editor (existing — independent toggle, distinct form). */}
                {canEditLeadership && isEditingLeadership && (
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
                        <form onSubmit={submitLeadership} className="px-5 py-5">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <TextFieldRow label="Leader name" id="leader_name" form={leadershipForm} />
                                <TextFieldRow label="Leader phone" id="leader_phone" form={leadershipForm} />
                                <TextFieldRow label="Assistant name" id="assistant_name" form={leadershipForm} />
                                <TextFieldRow label="Assistant phone" id="assistant_phone" form={leadershipForm} />
                                <TextFieldRow label="Welfare officer name" id="welfare_officer_name" form={leadershipForm} />
                                <TextFieldRow label="Welfare officer phone" id="welfare_officer_phone" form={leadershipForm} />
                            </div>
                            <div className="mt-5 flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={cancelEditingLeadership}
                                    className="inline-flex items-center rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={leadershipForm.processing}
                                    className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:cursor-not-allowed disabled:opacity-60"
                                    data-testid="impact-cell-edit-submit"
                                >
                                    {leadershipForm.processing ? 'Saving…' : 'Save leadership team'}
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Details CARD (read-only display) */}
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

                    {/* Phase 17 — Sub-cells card. Read-only when not managing; full editor (assign + promote) when toggled. */}
                    <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800 lg:col-span-2" data-testid="sub-cells-card">
                        <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    {layersIconPath}
                                </svg>
                            </span>
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Sub-cells</h3>
                            <span className="ml-auto text-xs text-gray-500 dark:text-gray-400">{subCells.length}</span>
                        </header>

                        {/* MANAGER MODE — admin-only toggle engaged. */}
                        {canEditDetails && isManagingSubCells ? (
                            <div className="space-y-5 px-5 py-5" data-testid="impact-cell-manage-subcells-card">
                                <div>
                                    <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Currently attached ({subCells.length})</h4>
                                    {subCells.length === 0 ? (
                                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">No sub-cells attached yet. Use the picker below to attach one.</p>
                                    ) : (
                                        <ul className="mt-2 space-y-2">
                                            {subCells.map((s) => (
                                                <li key={s.id} className="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-sm dark:border-gray-700" data-testid={`sub-cell-manage-row-${s.id}`}>
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium text-gray-900 dark:text-gray-100">{s.name}</span>
                                                        <StatusPill tone="info" dot>Sub-cell</StatusPill>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => promoteToPrimary(s.id, s.name)}
                                                        className="inline-flex items-center gap-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800 transition-colors hover:bg-amber-100 dark:border-amber-700/40 dark:bg-amber-900/20 dark:text-amber-200 dark:hover:bg-amber-900/40"
                                                        data-testid={`sub-cell-promote-${s.id}`}
                                                    >
                                                        ↗ Make primary
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>

                                <div className="border-t border-gray-100 pt-4 dark:border-gray-700">
                                    <h4 className="text-xs font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Attach a primary as a sub-cell</h4>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Picks a primary cell eligible for reassignment (its own children would block the move).
                                    </p>
                                    <form onSubmit={attachSelectedCell} className="mt-3 flex flex-wrap items-end gap-2">
                                        <div className="flex-1 min-w-[220px]">
                                            <InputLabel htmlFor="child_id" value="Choose primary to attach" />
                                            <select
                                                id="child_id"
                                                value={subCellsForm.data.child_id}
                                                onChange={(e) => subCellsForm.setData('child_id', e.target.value)}
                                                className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                                data-testid="impact-cell-attach-subcell-select"
                                                required
                                            >
                                                <option value="">— Select primary —</option>
                                                {attachablePrims.map((p) => (
                                                    <option key={p.id} value={p.id}>{p.name}</option>
                                                ))}
                                            </select>
                                            <InputError message={subCellsForm.errors.child_id} />
                                        </div>
                                        <button
                                            type="submit"
                                            disabled={subCellsForm.processing || ! subCellsForm.data.child_id}
                                            className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:cursor-not-allowed disabled:opacity-60"
                                            data-testid="impact-cell-attach-subcell-submit"
                                        >
                                            {subCellsForm.processing ? 'Attaching…' : 'Attach as sub-cell'}
                                        </button>
                                    </form>
                                    {attachablePrims.length === 0 && (
                                        <p className="mt-2 text-xs text-amber-600 dark:text-amber-300">
                                            No primaries currently eligible (all candidates have their own sub-cells, which would form a 3-level hierarchy).
                                        </p>
                                    )}
                                </div>

                                <div className="flex justify-end border-t border-gray-100 pt-3 dark:border-gray-700">
                                    <button
                                        type="button"
                                        onClick={cancelManagingSubCells}
                                        className="inline-flex items-center rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                    >
                                        Close
                                    </button>
                                </div>
                            </div>
                        ) : (
                            /* READ-ONLY — original Phase 13 surface. */
                            <div className="px-5 py-4">
                                {subCells.length === 0 ? (
                                    <EmptyState
                                        title="No sub-cells"
                                        description={cell.is_primary ? 'This primary has no sub-cells assigned to it.' : 'Sub-cells can only exist under a primary cell.'}
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
                        )}
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
 * Phase 13 — Leadership Team edit payload (free-text fields only;
 * details fields are sent in a separate useForm to keep editor state
 * fully independent).
 */
interface ImpactCellLeadershipPayload {
    leader_name: string;
    leader_phone: string;
    assistant_name: string;
    assistant_phone: string;
    welfare_officer_name: string;
    welfare_officer_phone: string;
}

function cellToLeadershipPayload(cell: ImpactCellDetail): ImpactCellLeadershipPayload {
    return {
        leader_name: cell.leader_name ?? '',
        leader_phone: cell.leader_phone ?? '',
        assistant_name: cell.assistant_name ?? '',
        assistant_phone: cell.assistant_phone ?? '',
        welfare_officer_name: cell.welfare_officer_name ?? '',
        welfare_officer_phone: cell.welfare_officer_phone ?? '',
    };
}

type LeadershipField = keyof ImpactCellLeadershipPayload;

/**
 * Phase 17 — Details edit payload (the cell's identity fields,
 * the cell-type toggle, and the parent picker). Mirrors the keys
 * `ImpactCellController::validateCell()` accepts on the existing PUT
 * route (minus the leadership fields, which live in their own editor).
 */
interface ImpactCellDetailsPayload {
    name: string;
    phone: string;
    address: string;
    parent_cell_id: string;
    is_primary: boolean;
    order: number;
}

function cellToDetailsPayload(cell: ImpactCellDetail): ImpactCellDetailsPayload {
    return {
        name: cell.name,
        phone: cell.phone ?? '',
        address: cell.address ?? '',
        parent_cell_id: cell.parent_cell_id ?? '',
        is_primary: cell.is_primary,
        // Phase 32 — carry the real order through instead of hardcoding 0,
        // so a details save no longer silently resets the cell's ordering.
        order: cell.order ?? 0,
    };
}

type DetailsFieldId = keyof ImpactCellDetailsPayload;

/**
 * Inline editable text-input row bound to `form` (useForm). Generic on
 * `K extends DetailsFieldId` so `id` is type-safe and the `setData`
 * call has no casts.
 */
function DetailsField({ label, id, form, type = 'text', required = false, maxLength }: {
    label: string;
    id: DetailsFieldId;
    form: ReturnType<typeof useForm<ImpactCellDetailsPayload>>;
    type?: 'text' | 'number';
    required?: boolean;
    maxLength?: number;
}) {
    const value = form.data[id];
    const error = form.errors[id];
    return (
        <div className="space-y-1.5">
            <InputLabel htmlFor={id} value={label} />
            <input
                id={id}
                name={id}
                type={type}
                value={value as string | number}
                onChange={(e) => form.setData(id, type === 'number' ? Number(e.target.value) : e.target.value)}
                className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                data-testid={`impact-cell-edit-details-${id}`}
                required={required}
                maxLength={maxLength}
                min={type === 'number' ? 0 : undefined}
            />
            <InputError message={error} />
        </div>
    );
}

/**
 * Inline editable text-input row for the Leadership Team card (Phase 13).
 */
function TextFieldRow({ label, id, form }: {
    label: string;
    id: LeadershipField;
    form: ReturnType<typeof useForm<ImpactCellLeadershipPayload>>;
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
