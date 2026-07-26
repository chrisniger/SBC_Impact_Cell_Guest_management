import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import KPICard from '@/Components/KPICard';
import LeadershipBoard from '@/Components/LeadershipBoard';
import StatusPill from '@/Components/StatusPill';
import ViewOnlyBanner from '@/Components/ViewOnlyBanner';
import { Head, Link, router } from '@inertiajs/react';

type OfficerKpis = {
    pendingContacts: number;
    totalCalls: number;
    visited: number;
    pendingVisit: number;
    responseRate: number;
};

type TeamKpis = {
    pendingContacts: number;
    contactedToday: number;
    wrongNumber: number;
    notReachable: number;
};

type QueueRow = {
    id: string;
    guestName: string;
    phone: string | null;
    contactedStatus: string | null;
    visited: boolean;
    createdAt: string | null;
};

type TeamQueueRow = {
    id: string;
    guestName: string;
    phone: string | null;
    followUpStatus: string | null;
    latestContact: string | null;
    officerName: string | null;
    updatedAt: string | null;
};

type LeaderKpis = {
    cellName: string;
    memberCount: number;
    weekSubmissions: number;
    totalSubmissions: number;
};

type AdminKpis = {
    totalGuests: number;
    pendingContacts: number;
    totalCalls: number;
    visited: number;
    totalCells: number;
    totalSubmissions: number;
    totalUsers: number;
};

type RecentSubmission = {
    id: string;
    type: string;
    cellName: string | null;
    preview: string;
    createdAt: string | null;
};

type ZonalKpis = {
    totalCells: number;
    totalSubmissions: number;
    pendingGuests: number;
    contactedGuests: number;
};

type ZonalCell = { id: string; name: string; is_primary: boolean };

type DashboardPageProps = {
    variant: 'officer' | 'team' | 'impactCell' | 'zonal' | 'admin';
    kpis: OfficerKpis | TeamKpis | LeaderKpis | AdminKpis | ZonalKpis | null;
    queue: QueueRow[] | TeamQueueRow[];
    recentSubmissions?: RecentSubmission[];
    zonalCells?: ZonalCell[];
    zonalSubmissions?: RecentSubmission[];
    primaryCellId?: string | null;
    activeRole: string | null;
    activeGroup: string | null;
};

/**
 * Dashboard — Phase 05 / Phase 06.
 *
 * Selects layout by `props.variant`. The variant is decided server-side
 * in `DashboardController::index()` based on `User::activeGroup()` — the
 * single source of truth. New groups (Team, Cell Leader) will add new
 * variants in Phase 06 / Phase 07.
 */
export default function Dashboard({ variant, kpis, queue, recentSubmissions, zonalCells, zonalSubmissions, primaryCellId, activeRole, activeGroup }: DashboardPageProps) {
    return (
        <AuthenticatedLayout
            header={
                variant === 'officer'
                    ? <OfficerHeader activeRole={activeRole} />
                    : variant === 'team'
                    ? <TeamHeader activeRole={activeRole} activeGroup={activeGroup} />
                    : variant === 'impactCell'
                    ? <LeaderHeader activeRole={activeRole} cellName={(kpis as LeaderKpis)?.cellName} />
                    : variant === 'zonal'
                    ? <ZonalHeader activeRole={activeRole} />
                    : <AdminHeader activeRole={activeRole} activeGroup={activeGroup} />
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <ViewOnlyBanner role={activeRole} />
                    {variant === 'officer' ? (
                        <OfficerDashboard kpis={kpis as OfficerKpis} queue={queue as QueueRow[]} />
                    ) : variant === 'team' ? (
                        <TeamDashboard kpis={kpis as TeamKpis} queue={queue as TeamQueueRow[]} activeRole={activeRole} />
                    ) : variant === 'impactCell' ? (
                        <LeaderDashboard kpis={kpis as LeaderKpis} recentSubmissions={recentSubmissions ?? []} primaryCellId={primaryCellId} />
                    ) : variant === 'zonal' ? (
                        <ZonalDashboard kpis={kpis as ZonalKpis} cells={zonalCells ?? []} submissions={zonalSubmissions ?? []} />
                    ) : (
                        <AdminDashboard kpis={kpis as AdminKpis} />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function OfficerHeader({ activeRole }: { activeRole: string | null }) {
    return (
        <div>
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Follow Up Officer Dashboard
            </h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Active role: <span className="font-mono">{activeRole ?? '—'}</span> · Your personal KPIs and queue
            </p>
        </div>
    );
}

function TeamHeader({ activeRole, activeGroup }: { activeRole: string | null; activeGroup: string | null }) {
    return (
        <div>
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Team Dashboard
            </h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Active role: <span className="font-mono">{activeRole ?? '—'}</span> · Team-wide queue and KPIs
            </p>
        </div>
    );
}

function LeaderHeader({ activeRole, cellName }: { activeRole: string | null; cellName: string | undefined }) {
    return (
        <div>
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Impact Cell Dashboard
            </h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Active role: <span className="font-mono">{activeRole ?? '—'}</span> · {cellName ?? 'No cell assigned'} · Weekly submissions
            </p>
        </div>
    );
}

function AdminHeader({ activeRole, activeGroup }: { activeRole: string | null; activeGroup: string | null }) {
    return (
        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Dashboard
        </h2>
    );
}

function FollowUpStatusPill({ status }: { status: string | null }) {
    if (status === null || status === '' || status === 'NOT CONTACTED') {
        return <StatusPill tone="warn">Not Contacted</StatusPill>;
    }
    if (status === 'CONTACTED') {
        return <StatusPill tone="success">Contacted</StatusPill>;
    }
    return <StatusPill tone="neutral">{status}</StatusPill>;
}

function InlineStatusSelect({ guestId, currentStatus, isViewOnly }: { guestId: string; currentStatus: string | null; isViewOnly: boolean }) {
    const handleChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        if (isViewOnly) return;
        const newStatus = e.target.value || null;
        router.patch(
            route('guests.follow-up-status', guestId),
            { follow_up_status: newStatus },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    e.target.value = currentStatus ?? '';
                },
            },
        );
    };

    return (
        <select
            value={currentStatus ?? ''}
            onChange={handleChange}
            disabled={isViewOnly}
            className={`block w-full max-w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 ${
                isViewOnly ? 'cursor-not-allowed opacity-60' : ''
            }`}
            title={isViewOnly ? 'View-only mode — cannot edit' : 'Update follow-up status'}
        >
            <option value="">— (unset) —</option>
            <option value="NOT CONTACTED">Not Contacted</option>
            <option value="CONTACTED">Contacted</option>
            <option value="WRONG NUMBER">Wrong Number</option>
            <option value="NOT REACHABLE">Not Reachable</option>
        </select>
    );
}

function OfficerDashboard({ kpis, queue }: { kpis: OfficerKpis; queue: QueueRow[] }) {
    return (
        <div className="space-y-6">
            {/* Hero row: 5 KPI cards */}
            <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <KPICard
                    caption="Pending Contacts"
                    value={kpis.pendingContacts}
                    trend="≤ pending outreach"
                />
                <KPICard
                    caption="Total Calls"
                    value={kpis.totalCalls}
                    trend="guests contacted"
                />
                <KPICard
                    caption="Visited"
                    value={kpis.visited}
                    trend="confirmed visits"
                />
                <KPICard
                    caption="Pending Visit"
                    value={kpis.pendingVisit}
                    trend="available, awaiting visit"
                />
                <KPICard
                    caption="Response Rate"
                    value={`${kpis.responseRate.toFixed(1)}%`}
                    trend="visited ÷ total calls"
                />
            </section>

            {/* My queue: top 8 NOT CONTACTED first */}
            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
                        My Queue
                    </h3>
                    <Link
                        href={route('guests.index')}
                        className="text-sm text-red-600 hover:underline dark:text-red-400"
                    >
                        See all in My Guests →
                    </Link>
                </div>

                {queue.length === 0 ? (
                    <EmptyState />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Guest</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Phone</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Visited</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Added</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {queue.map((g) => (
                                    <tr key={g.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td className="px-4 py-3 text-sm">
                                            <Link
                                                href={route('guests.show', g.id)}
                                                className="font-medium text-gray-900 hover:text-red-600 dark:text-gray-100 dark:hover:text-red-400"
                                            >
                                                {g.guestName}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {g.phone ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <ContactedStatusPill status={g.contactedStatus} />
                                        </td>
                                        <td className="px-4 py-3">
                                            {g.visited
                                                ? <StatusPill tone="success">Visited</StatusPill>
                                                : <StatusPill tone="neutral">Pending</StatusPill>}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {g.createdAt?.slice(0, 10) ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

function TeamDashboard({ kpis, queue, activeRole }: { kpis: TeamKpis; queue: TeamQueueRow[]; activeRole: string | null }) {
    const isViewOnly = activeRole === 'Follow_UP_View_Only';

    return (
        <div className="space-y-6">
            {/* Hero row: 4 team KPI cards */}
            <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <KPICard
                    caption="Pending Contacts"
                    value={kpis.pendingContacts}
                    trend="not yet contacted"
                />
                <KPICard
                    caption="Contacted Today"
                    value={kpis.contactedToday}
                    trend="contact sections logged today"
                />
                <KPICard
                    caption="Wrong Number"
                    value={kpis.wrongNumber}
                    trend="marked wrong number"
                />
                <KPICard
                    caption="Not Reachable"
                    value={kpis.notReachable}
                    trend="could not be reached"
                />
            </section>

            {/* Team queue: inline status editing */}
            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
                        Team Queue
                    </h3>
                    <Link
                        href={route('guests.index')}
                        className="text-sm text-red-600 hover:underline dark:text-red-400"
                    >
                        See all guests →
                    </Link>
                </div>

                {queue.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-600 dark:bg-gray-800">
                        <p className="text-base font-medium text-gray-800 dark:text-gray-200">
                            No guests in the queue
                        </p>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            When guests are added, they'll appear here sorted by priority.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Guest</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Phone</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Officer</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Follow Up Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Latest Contact</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Updated</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {queue.map((g) => (
                                    <tr key={g.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td className="px-4 py-3 text-sm">
                                            <Link
                                                href={route('guests.show', g.id)}
                                                className="font-medium text-gray-900 hover:text-red-600 dark:text-gray-100 dark:hover:text-red-400"
                                            >
                                                {g.guestName}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {g.phone ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                            {g.officerName ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {isViewOnly ? (
                                                <FollowUpStatusPill status={g.followUpStatus} />
                                            ) : (
                                                <InlineStatusSelect
                                                    guestId={g.id}
                                                    currentStatus={g.followUpStatus}
                                                    isViewOnly={false}
                                                />
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {g.latestContact ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {g.updatedAt?.slice(0, 10) ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {!isViewOnly && g.followUpStatus !== 'CONTACTED' && (
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        router.patch(
                                                            route('guests.follow-up-status', g.id),
                                                            { follow_up_status: 'CONTACTED' },
                                                            { preserveScroll: true, preserveState: true },
                                                        );
                                                    }}
                                                    className="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                                >
                                                    Mark Contacted
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

function ContactedStatusPill({ status }: { status: string | null }) {
    if (status === null || status === '' || status === 'No' || status === 'Not Contacted') {
        return <StatusPill tone="warn">Not Contacted</StatusPill>;
    }
    if (status === 'AvailableForVisit') {
        return <StatusPill tone="brand">Available for Visit</StatusPill>;
    }
    if (status === 'Visited') {
        return <StatusPill tone="success">Visited</StatusPill>;
    }
    return <StatusPill tone="neutral">{status}</StatusPill>;
}

function EmptyState() {
    return (
        <div className="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-600 dark:bg-gray-800">
            <p className="text-base font-medium text-gray-800 dark:text-gray-200">
                No guests assigned yet
            </p>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                When an admin assigns a guest to you, they'll appear here.
            </p>
        </div>
    );
}

const submissionTypeLabels: Record<string, string> = {
    member: 'Members Data',
    report: 'Cell Report',
    childbirth: 'Childbirth',
    soul: 'Soul',
};

type LeaderDashboardProps = {
    kpis: LeaderKpis;
    recentSubmissions: RecentSubmission[];
    primaryCellId?: string | null;
};

function LeaderDashboard({ kpis, recentSubmissions, primaryCellId }: LeaderDashboardProps) {
    return (
        <div className="space-y-6">
            {primaryCellId && <LeadershipBoard cellId={primaryCellId} canView={true} />}

            <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <KPICard caption="Cell" value={kpis.cellName} trend={kpis.memberCount > 0 ? `${kpis.memberCount} members` : 'No members'} />
                <KPICard caption="Members" value={kpis.memberCount} trend="registered in cell" />
                <KPICard caption="This Week" value={kpis.weekSubmissions} trend="submissions this week" />
                <KPICard caption="Total" value={kpis.totalSubmissions} trend="all submissions" />
            </section>

            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
                        Quick Submit
                    </h3>
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {(['member', 'report', 'childbirth', 'soul'] as const).map((type) => (
                        <Link
                            key={type}
                            href={`/impact-submissions/create?type=${type}`}
                            className="flex items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white px-4 py-6 text-sm font-medium text-gray-700 hover:border-red-400 hover:text-red-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-red-500 dark:hover:text-red-400"
                        >
                            {submissionTypeLabels[type]}
                        </Link>
                    ))}
                </div>
            </section>

            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
                        Recent Submissions
                    </h3>
                    <Link
                        href={route('impact-submissions.my-reports')}
                        className="text-sm text-red-600 hover:underline dark:text-red-400"
                    >
                        View all my reports →
                    </Link>
                </div>
                {recentSubmissions.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-600 dark:bg-gray-800">
                        <p className="text-base font-medium text-gray-800 dark:text-gray-200">
                            No submissions yet
                        </p>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Use the quick-submit links above to get started.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Cell</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Preview</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {recentSubmissions.map((s) => (
                                    <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td className="px-4 py-3 text-sm capitalize">{submissionTypeLabels[s.type] ?? s.type}</td>
                                        <td className="px-4 py-3 text-sm">{s.cellName ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{s.preview}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{s.createdAt?.slice(0, 10) ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

function ZonalHeader({ activeRole }: { activeRole: string | null }) {
    return (
        <div>
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Zonal Coordinator Dashboard
            </h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Active role: <span className="font-mono">{activeRole ?? '—'}</span> · Zone-wide overview
            </p>
        </div>
    );
}

function ZonalDashboard({ kpis, cells, submissions }: { kpis: ZonalKpis; cells: ZonalCell[]; submissions: RecentSubmission[] }) {
    return (
        <div className="space-y-6">
            <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <KPICard caption="Impact Cells" value={kpis.totalCells} trend="in your zone" />
                <KPICard caption="Total Submissions" value={kpis.totalSubmissions} trend="all types" />
                <KPICard caption="Pending Guests" value={kpis.pendingGuests} trend="not yet contacted" />
                <KPICard caption="Contacted Guests" value={kpis.contactedGuests} trend="follow-up made" />
            </section>

            <section>
                <h3 className="mb-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Impact Cells</h3>
                {cells.length === 0 ? (
                    <p className="text-sm text-gray-500">No cells assigned.</p>
                ) : (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {cells.map(c => (
                            <Link key={c.id} href={route('impact-submissions.index')}
                                className="rounded-xl border border-gray-200 bg-white p-3 text-sm hover:border-red-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <span className="font-medium text-gray-900 dark:text-gray-100">{c.name}</span>
                                {c.is_primary && <span className="ml-2 text-xs text-red-500">Primary</span>}
                            </Link>
                        ))}
                    </div>
                )}
            </section>

            <section>
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="text-sm font-semibold text-gray-600 dark:text-gray-400">Recent Submissions</h3>
                    <Link href={route('impact-submissions.index')} className="text-xs text-red-600 hover:underline">View all →</Link>
                </div>
                {submissions.length === 0 ? (
                    <p className="text-sm text-gray-500">No submissions yet.</p>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead className="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Cell</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Preview</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {submissions.map(s => (
                                    <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td className="px-4 py-3 text-sm capitalize">{s.type}</td>
                                        <td className="px-4 py-3 text-sm">{s.cellName ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{s.preview}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{s.createdAt?.slice(0, 10) ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </div>
    );
}

function AdminDashboard({ kpis }: { kpis: AdminKpis }) {
    return (
        <div className="space-y-6">
            <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <KPICard caption="Total Guests" value={kpis.totalGuests} trend="all records" />
                <KPICard caption="Pending Contacts" value={kpis.pendingContacts} trend="not yet contacted" />
                <KPICard caption="Total Calls Made" value={kpis.totalCalls} trend="contacted" />
                <KPICard caption="Visited" value={kpis.visited} trend="confirmed visits" />
            </section>
            <section className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <KPICard caption="Impact Cells" value={kpis.totalCells} trend="registered cells" />
                <KPICard caption="Submissions" value={kpis.totalSubmissions} trend="across all types" />
                <KPICard caption="Users" value={kpis.totalUsers} trend="system accounts" />
            </section>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <QuickLinkCard href={route('guests.index')} label="Manage Guests" />
                <QuickLinkCard href={route('impact-cells.index')} label="Impact Cells" />
                <QuickLinkCard href={route('reports.index')} label="View Reports" />
                <QuickLinkCard href={route('csv.import')} label="CSV Import" />
            </div>
        </div>
    );
}

function QuickLinkCard({ href, label }: { href: string; label: string }) {
    return (
        <Link
            href={href}
            className="flex items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white px-4 py-5 text-sm font-medium text-gray-700 hover:border-red-400 hover:text-red-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-red-500 dark:hover:text-red-400"
        >
            {label}
        </Link>
    );
}
