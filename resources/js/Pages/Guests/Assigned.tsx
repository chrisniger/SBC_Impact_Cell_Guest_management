import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import EmptyState from '@/Components/EmptyState';
import StatusPill from '@/Components/StatusPill';
import { Head, Link } from '@inertiajs/react';
import { useCallback, useState } from 'react';

interface CellStat {
    id: string;
    name: string;
    totalAssigned: number;
    totalContacted: number;
    totalPending: number;
}

interface RosterGuest {
    id: string;
    guestName: string;
    phone: string | null;
    impactStatus: string | null;
    contactedStatus: string | null;
    createdAt: string | null;
}

interface RosterState {
    status: 'idle' | 'loading' | 'error';
    guests: RosterGuest[];
}

interface AssignedPageProps {
    cells: CellStat[];
    totals: {
        totalCells: number;
        totalAssigned: number;
        totalContacted: number;
        totalPending: number;
    };
    activeRole: string | null;
}

const gridIconPath = <><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="14" y="14" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /></>;
const usersIconPath = <><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></>;
const checkIconPath = <><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></>;
const clockIconPath = <><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></>;

function SummaryCard({ label, value, icon, accent }: { label: string; value: number; icon: React.ReactNode; accent: string }) {
    return (
        <div className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-card dark:border-gray-700 dark:bg-gray-800">
            <div className="flex items-center gap-3">
                <span className={`inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${accent}`}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5" aria-hidden="true">
                        {icon}
                    </svg>
                </span>
                <div className="min-w-0">
                    <p className="truncate text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{label}</p>
                    <p className="mt-0.5 text-2xl font-bold tabular-nums tracking-tight text-gray-900 dark:text-white">{value}</p>
                </div>
            </div>
        </div>
    );
}

function RosterStatusBadge({ value }: { value: string | null }) {
    if (!value) return <span className="text-gray-400 dark:text-gray-500">—</span>;
    const tone =
        value === 'Contacted' ? 'success' :
        value === 'Not Contacted' ? 'warn' :
        value === 'Not Reachable' ? 'danger' :
        'neutral';
    return <StatusPill tone={tone}>{value}</StatusPill>;
}

export default function Assigned({ cells, totals, activeRole }: AssignedPageProps) {
    const [expandedId, setExpandedId] = useState<string | null>(null);
    const [rosters, setRosters] = useState<Record<string, RosterState>>({});

    // Fetch (or re-fetch) one cell's roster. Kept OUT of the toggle updater
    // so React state updaters stay pure (StrictMode double-invokes updaters
    // in dev — a fetch inside one would fire twice).
    const loadRoster = useCallback((cellId: string) => {
        setRosters((r) => ({ ...r, [cellId]: { status: 'loading', guests: [] } }));
        fetch(route('guests.roster', { cell: cellId }))
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then((payload: { guests?: RosterGuest[] }) => {
                setRosters((r) => ({
                    ...r,
                    [cellId]: { status: 'idle', guests: payload.guests ?? [] },
                }));
            })
            .catch(() => {
                setRosters((r) => ({ ...r, [cellId]: { status: 'error', guests: [] } }));
            });
    }, []);

    // Single-open accordion: expanding a cell collapses the previous one.
    // First expand lazy-loads the roster; later expands hit the cache.
    const toggleRoster = useCallback((cellId: string) => {
        const willOpen = expandedId !== cellId;
        setExpandedId(willOpen ? cellId : null);
        if (willOpen && !rosters[cellId]) {
            loadRoster(cellId);
        }
    }, [expandedId, rosters, loadRoster]);

    const assignedCells = cells.filter((c) => c.totalAssigned > 0);

    return (
        <AdminDashboardLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Guests · Impact Cell Administrator
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Assigned Guests
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Contact progress per Impact Cell — cells mark guests assigned to them from <span className="font-semibold">Not Contacted</span> to <span className="font-semibold">Contacted</span>. Click any cell to expand its roster.
                    </p>
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-500">
                        Active role: <span className="font-mono">{activeRole ?? '—'}</span>
                    </p>
                </div>
            }
        >
            <Head title="Assigned Guests" />

            <div className="space-y-6">
                {/* Summary cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Impact Cells" value={totals.totalCells} icon={gridIconPath} accent="bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300" />
                    <SummaryCard label="Total Assigned" value={totals.totalAssigned} icon={usersIconPath} accent="bg-blue-50 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300" />
                    <SummaryCard label="Total Contacted" value={totals.totalContacted} icon={checkIconPath} accent="bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300" />
                    <SummaryCard label="Total Pending" value={totals.totalPending} icon={clockIconPath} accent="bg-amber-50 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300" />
                </div>

                {cells.length === 0 ? (
                    <EmptyState
                        title="No impact cells yet"
                        description="Once impact cells exist, this table shows the assigned / contacted / pending breakdown for each one."
                        iconPath={gridIconPath}
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="assigned-guests-table">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="w-10 px-2 py-3" aria-label="Expand" />
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Impact Cell</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Assigned</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Contacted</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Pending</th>
                                        <th className="hidden px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 md:table-cell">Progress</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Roster</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {cells.map((c) => {
                                        const pct = c.totalAssigned > 0 ? Math.round((c.totalContacted / c.totalAssigned) * 100) : 0;
                                        const isOpen = expandedId === c.id;
                                        const roster = rosters[c.id];
                                        return (
                                            <RosterRowGroup
                                                key={c.id}
                                                cell={c}
                                                pct={pct}
                                                isOpen={isOpen}
                                                roster={roster}
                                                onToggle={() => toggleRoster(c.id)}
                                                onRetry={() => loadRoster(c.id)}
                                            />
                                        );
                                    })}
                                </tbody>
                                <tfoot className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <td className="px-2 py-3" />
                                        <td className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Total</td>
                                        <td className="px-4 py-3 text-right text-sm font-bold tabular-nums text-gray-900 dark:text-white">{totals.totalAssigned}</td>
                                        <td className="px-4 py-3 text-right text-sm font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{totals.totalContacted}</td>
                                        <td className="px-4 py-3 text-right text-sm font-bold tabular-nums text-amber-600 dark:text-amber-400">{totals.totalPending}</td>
                                        <td className="hidden px-4 py-3 md:table-cell" />
                                        <td className="px-4 py-3" />
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div className="border-t border-gray-200 bg-gray-50/60 px-4 py-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900/40">
                            <span className="font-mono">{totals.totalAssigned}</span> guests assigned across{' '}
                            <span className="font-mono">{assignedCells.length}</span> of{' '}
                            <span className="font-mono">{totals.totalCells}</span> impact cells · Click the chevron on any cell to expand its roster inline.
                        </div>
                    </div>
                )}
            </div>
        </AdminDashboardLayout>
    );
}

/** One cell's main row + (when open) its inline roster panel. */
function RosterRowGroup({
    cell,
    pct,
    isOpen,
    roster,
    onToggle,
    onRetry,
}: {
    cell: CellStat;
    pct: number;
    isOpen: boolean;
    roster: RosterState | undefined;
    onToggle: () => void;
    onRetry: () => void;
}) {
    return (
        <>
            <tr className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                <td className="px-2 py-3 text-center">
                    <button
                        type="button"
                        onClick={onToggle}
                        aria-expanded={isOpen}
                        aria-label={`Expand roster for ${cell.name}`}
                        title={isOpen ? 'Collapse roster' : 'Expand roster'}
                        data-testid={`assigned-expand-${cell.name}`}
                        className="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-400 transition-all hover:bg-indigo-100 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className={`h-4 w-4 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                            aria-hidden="true"
                        >
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </button>
                </td>
                <td className="px-4 py-3">
                    <Link
                        href={route('guests.index', { cell: cell.id })}
                        className="font-medium text-gray-900 transition-colors hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                    >
                        {cell.name}
                    </Link>
                </td>
                <td className="px-4 py-3 text-right text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">{cell.totalAssigned}</td>
                <td className="px-4 py-3 text-right text-sm font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">{cell.totalContacted}</td>
                <td className="px-4 py-3 text-right text-sm font-semibold tabular-nums text-amber-600 dark:text-amber-400">{cell.totalPending}</td>
                <td className="hidden px-4 py-3 md:table-cell">
                    <div className="flex items-center gap-2">
                        <div className="h-2 w-full max-w-[140px] overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div
                                className="h-full rounded-full bg-emerald-500 transition-[width] duration-500"
                                style={{ width: `${pct}%` }}
                                data-testid={`assigned-progress-${cell.name}`}
                            />
                        </div>
                        <span className="w-9 text-right text-xs font-medium tabular-nums text-gray-500 dark:text-gray-400">{pct}%</span>
                    </div>
                </td>
                <td className="px-4 py-3 text-right">
                    <Link
                        href={route('guests.index', { cell: cell.id })}
                        className="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-500/50 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        View
                    </Link>
                </td>
            </tr>
            {isOpen && (
                <tr className="bg-indigo-50/40 dark:bg-gray-900/40" data-testid={`assigned-roster-${cell.name}`}>
                    <td colSpan={7} className="px-6 py-4">
                        <div className="overflow-hidden rounded-lg border border-indigo-100 bg-white dark:border-indigo-900/40 dark:bg-gray-800/80">
                            <div className="flex items-center justify-between border-b border-indigo-100 px-4 py-2.5 dark:border-indigo-900/40">
                                <p className="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                                    Guest roster · {cell.name}
                                </p>
                                {roster?.status === 'idle' && (
                                    <span className="text-xs text-gray-500 dark:text-gray-400">
                                        {roster.guests.length} guest{roster.guests.length === 1 ? '' : 's'}
                                    </span>
                                )}
                            </div>
                            <div className="p-4">
                                {roster?.status === 'loading' && (
                                    <div className="flex items-center justify-center gap-2 py-6 text-sm text-gray-500 dark:text-gray-400">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 animate-spin" aria-hidden="true">
                                            <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                                        </svg>
                                        Loading roster…
                                    </div>
                                )}
                                {roster?.status === 'error' && (
                                    <div className="flex items-center justify-between gap-3 py-4">
                                        <p className="text-sm text-rose-600 dark:text-rose-400">Couldn't load this roster.</p>
                                        <button
                                            type="button"
                                            onClick={onRetry}
                                            className="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-rose-300 hover:text-rose-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        >
                                            Retry
                                        </button>
                                    </div>
                                )}
                                {roster?.status === 'idle' && roster.guests.length === 0 && (
                                    <p className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No guests assigned to this cell yet.
                                    </p>
                                )}
                                {roster?.status === 'idle' && roster.guests.length > 0 && (
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b border-gray-100 text-left dark:border-gray-700">
                                                    <th className="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                                                    <th className="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Phone</th>
                                                    <th className="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Impact</th>
                                                    <th className="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Contacted</th>
                                                    <th className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Added</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700/60">
                                                {roster.guests.map((g) => (
                                                    <tr key={g.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                                        <td className="px-3 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">{g.guestName}</td>
                                                        <td className="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{g.phone ?? '—'}</td>
                                                        <td className="px-3 py-2"><RosterStatusBadge value={g.impactStatus} /></td>
                                                        <td className="px-3 py-2"><RosterStatusBadge value={g.contactedStatus} /></td>
                                                        <td className="px-3 py-2 text-right text-sm text-gray-500 dark:text-gray-400">{g.createdAt?.slice(0, 10) ?? '—'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}
