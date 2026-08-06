import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import EmptyState from '@/Components/EmptyState';
import StatusPill from '@/Components/StatusPill';
import Modal from '@/Components/Modal';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface GuestRow {
    id: string;
    guest_name: string;
    date: string | null;
    event: string | null;
    source: string | null;
    contacted_status: string | null;
    impact_status: string | null;
    follow_up_status: string | null;
    follow_officer_id: string | null;
    nearest_impact_cell_id: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
}

interface GuestsPageProps {
    guests: {
        data: GuestRow[];
        links: { url: string | null; label: string; active: boolean }[];
        meta: {
            current_page: number;
            from: number | null;
            last_page: number;
            per_page: number;
            to: number | null;
            total: number;
        };
    };
    canCreate: boolean;
    editableFields: string[];
    activeRole: string | null;
    /** Phase 39 — set when Impact_Cell_Admin drills into one cell's roster (?cell=). */
    activeCellName?: string | null;
    groups: {
        ownedByGroup: string[];
        groupOf: string | null;
    };
}

const inboxIconPath = <><path d="M22 12h-6l-2 3h-4l-2-3H2" /><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" /></>;

function StatusBadge({ value }: { value: string | null }) {
    if (!value) return <span className="text-gray-400 dark:text-gray-500">—</span>;
    const tone =
        value === 'Visited' || value === 'CONTACTED' || value === 'AvailableForVisit' ? 'success' :
        value === 'No' || value === 'NOT CONTACTED' ? 'warn' :
        value === 'WRONG NUMBER' || value === 'NOT REACHABLE' ? 'danger' :
        'neutral';
    return <StatusPill tone={tone}>{value}</StatusPill>;
}

export default function Index({ guests, canCreate, editableFields, activeRole, activeCellName, groups }: GuestsPageProps) {
    const canEditAny = (editableFields ?? []).length > 0;
    const isAdmin = activeRole === 'Administrator';

    // Bulk delete state
    const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
    const [confirmDeleteOpen, setConfirmDeleteOpen] = useState(false);
    const [singleDeleteId, setSingleDeleteId] = useState<string | null>(null);
    const [deleting, setDeleting] = useState(false);

    const allSelected = guests.data.length > 0 && guests.data.every(g => selectedIds.has(g.id));

    const toggleSelect = (id: string) => {
        setSelectedIds(prev => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const toggleSelectAll = () => {
        if (allSelected) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(guests.data.map(g => g.id)));
        }
    };

    const handleSingleDelete = (id: string) => {
        setSingleDeleteId(id);
        setConfirmDeleteOpen(true);
    };

    const confirmSingleDelete = () => {
        if (!singleDeleteId) return;
        setDeleting(true);
        router.delete(route('guests.destroy', singleDeleteId), {
            onFinish: () => {
                setDeleting(false);
                setConfirmDeleteOpen(false);
                setSingleDeleteId(null);
            },
        });
    };

    const handleBulkDelete = () => {
        setConfirmDeleteOpen(true);
    };

    const confirmBulkDelete = () => {
        if (selectedIds.size === 0) return;
        setDeleting(true);
        router.post(route('guests.bulk-delete'), { ids: Array.from(selectedIds) }, {
            onFinish: () => {
                setDeleting(false);
                setConfirmDeleteOpen(false);
                setSelectedIds(new Set());
            },
        });
    };

    return (
        <AdminDashboardLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        {activeCellName ? 'Guests · Impact Cell Administrator' : 'My Guests'}
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {activeCellName
                            ? `Guests · ${activeCellName}`
                            : groups.groupOf === 'followUpTeam'
                                ? 'Assigned Guests'
                                : 'Guests'}
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Active role: <span className="font-mono">{activeRole ?? '—'}</span> · Group: <span className="font-mono">{groups.groupOf ?? '—'}</span> · You can edit: <span className="font-mono">{groups.ownedByGroup.length}</span> field{groups.ownedByGroup.length === 1 ? '' : 's'}
                    </p>
                </div>
            }
        >
            <Head title={activeCellName ? `Guests · ${activeCellName}` : 'Guests'} />

            <div className="space-y-4">
                {activeCellName && (
                    <div className="flex items-center justify-between gap-3 rounded-xl border border-indigo-200 bg-indigo-50/60 px-4 py-3 dark:border-indigo-900/40 dark:bg-indigo-950/30" data-testid="assigned-cell-banner">
                        <p className="text-sm text-indigo-900 dark:text-indigo-200">
                            Showing the roster of guests assigned to <span className="font-semibold">{activeCellName}</span>.
                        </p>
                        <Link
                            href={route('guests.index')}
                            className="shrink-0 inline-flex items-center gap-1.5 rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition-colors hover:bg-indigo-100/70 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-indigo-700 dark:bg-gray-800 dark:text-indigo-300 dark:hover:bg-gray-700"
                            data-testid="assigned-back-link"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                <line x1="19" y1="12" x2="5" y2="12" />
                                <polyline points="12 19 5 12 12 5" />
                            </svg>
                            Back to Assigned Guests
                        </Link>
                    </div>
                )}
                {guests.data.length === 0 ? (
                    <EmptyState
                        title="No guests yet"
                        description="Once guests are added or assigned to you, they'll appear here. Use the Search & filters above to narrow down."
                        iconPath={inboxIconPath}
                    />
                ) : (
                    <>
                        {/* Bulk action bar — only for Admin */}
                        {isAdmin && selectedIds.size > 0 && (
                            <div className="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50/60 px-4 py-2.5 dark:border-rose-800/40 dark:bg-rose-950/30" data-testid="bulk-delete-bar">
                                <span className="text-sm font-semibold text-rose-800 dark:text-rose-200">
                                    {selectedIds.size} guest{selectedIds.size === 1 ? '' : 's'} selected
                                </span>
                                <button
                                    type="button"
                                    onClick={handleBulkDelete}
                                    data-testid="bulk-delete-button"
                                    className="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-1"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                        <polyline points="3 6 5 6 21 6" />
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                        <line x1="10" y1="11" x2="10" y2="17" />
                                        <line x1="14" y1="11" x2="14" y2="17" />
                                    </svg>
                                    Delete Selected
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setSelectedIds(new Set())}
                                    className="inline-flex items-center gap-1 rounded-md border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 shadow-sm transition-colors hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500/20 dark:border-rose-700 dark:bg-gray-800 dark:text-rose-300 dark:hover:bg-gray-700"
                                >
                                    Clear
                                </button>
                            </div>
                        )}
                        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-testid="guests-table">
                                    <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                        <tr>
                                            {isAdmin && (
                                                <th className="w-10 px-3 py-3">
                                                    <input
                                                        type="checkbox"
                                                        checked={allSelected}
                                                        onChange={toggleSelectAll}
                                                        data-testid="select-all-guests"
                                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:border-gray-700 dark:bg-gray-900"
                                                    />
                                                </th>
                                            )}
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Event</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Contacted</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Impact</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Follow Up</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Created</th>
                                            {(canEditAny || isAdmin) && (
                                                <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                        {guests.data.map((g) => (
                                            <tr key={g.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                                {isAdmin && (
                                                    <td className="px-3 py-3">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedIds.has(g.id)}
                                                            onChange={() => toggleSelect(g.id)}
                                                            data-testid={`select-guest-${g.id}`}
                                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:border-gray-700 dark:bg-gray-900"
                                                        />
                                                    </td>
                                                )}
                                                <td className="px-4 py-3 text-sm">
                                                    <Link
                                                        href={route('guests.show', g.id)}
                                                        className="font-medium text-gray-900 transition-colors hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                                                    >
                                                        {g.guest_name}
                                                    </Link>
                                                </td>
                                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{g.event ?? '—'}</td>
                                                <td className="px-4 py-3"><StatusBadge value={g.contacted_status} /></td>
                                                <td className="px-4 py-3"><StatusBadge value={g.impact_status} /></td>
                                                <td className="px-4 py-3"><StatusBadge value={g.follow_up_status} /></td>
                                                <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{g.created_at?.slice(0, 10) ?? '—'}</td>
                                                {(canEditAny || isAdmin) && (
                                                <td className="px-4 py-3 text-right">
                                                    <div className="inline-flex items-center gap-1.5">
                                                        {canEditAny && (
                                                            <Link
                                                                href={route('guests.edit', g.id)}
                                                                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-500/50 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300"
                                                                data-testid={`guest-edit-${g.id}`}
                                                            >
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                                </svg>
                                                                Edit
                                                            </Link>
                                                        )}
                                                        {isAdmin && (
                                                            <button
                                                                type="button"
                                                                onClick={() => handleSingleDelete(g.id)}
                                                                data-testid={`guest-delete-${g.id}`}
                                                                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs font-semibold text-gray-600 shadow-sm transition-colors hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500/30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-rose-500/50 dark:hover:bg-rose-900/30 dark:hover:text-rose-300"
                                                            >
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                                                    <polyline points="3 6 5 6 21 6" />
                                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                                                </svg>
                                                                Delete
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {guests.links.length > 0 && (
                                <div className="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-gray-50/60 px-4 py-3 text-xs dark:border-gray-700 dark:bg-gray-900/40">
                                    <div className="text-gray-500 dark:text-gray-400">
                                        Showing <span className="font-mono">{guests.meta.from ?? 0}</span>–<span className="font-mono">{guests.meta.to ?? 0}</span> of <span className="font-mono">{guests.meta.total}</span>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-1">
                                        {guests.links.map((link, i) => (
                                            <Link
                                                key={i}
                                                href={link.url ?? '#'}
                                                preserveState
                                                className={`inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md px-2 text-xs font-medium transition-colors ${
                                                    link.active
                                                        ? 'bg-indigo-600 text-white shadow-sm'
                                                        : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                                } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </>
                )}

                {canCreate && (
                    <div className="flex justify-end">
                        <Link
                            href={route('guests.create')}
                            className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            Add Guest
                        </Link>
                    </div>
                )}
            </div>

            {/* Delete confirmation modal */}
            <Modal show={confirmDeleteOpen} onClose={() => { setConfirmDeleteOpen(false); setSingleDeleteId(null); }}>
                <div className="p-6">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-900/30">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5 text-rose-600 dark:text-rose-400" aria-hidden="true">
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                <line x1="12" y1="9" x2="12" y2="13" />
                                <line x1="12" y1="17" x2="12.01" y2="17" />
                            </svg>
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                                {singleDeleteId ? 'Delete Guest' : 'Delete Selected Guests'}
                            </h3>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                {singleDeleteId
                                    ? 'Are you sure you want to delete this guest? This action uses soft-delete and can be recovered by an administrator.'
                                    : `Are you sure you want to delete ${selectedIds.size} guest${selectedIds.size === 1 ? '' : 's'}? This action uses soft-delete and can be recovered by an administrator.`}
                            </p>
                        </div>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => { setConfirmDeleteOpen(false); setSingleDeleteId(null); }}
                            disabled={deleting}
                            className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={singleDeleteId ? confirmSingleDelete : confirmBulkDelete}
                            disabled={deleting}
                            data-testid="confirm-delete-button"
                            className="inline-flex items-center gap-2 rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {deleting ? (
                                <>
                                    <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 animate-spin" aria-hidden="true">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                    </svg>
                                    Deleting…
                                </>
                            ) : (
                                <>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                        <polyline points="3 6 5 6 21 6" />
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                    </svg>
                                    {singleDeleteId ? 'Delete Guest' : `Delete ${selectedIds.size} Guest${selectedIds.size === 1 ? '' : 's'}`}
                                </>
                            )}
                        </button>
                    </div>
                </div>
            </Modal>
        </AdminDashboardLayout>
    );
}
