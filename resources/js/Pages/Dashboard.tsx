import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import EmptyState from '@/Components/EmptyState';
import Greeting from '@/Components/Greeting';
import KPICard from '@/Components/KPICard';
import LeadershipBoard from '@/Components/LeadershipBoard';
import StatusPill from '@/Components/StatusPill';
import ViewOnlyBanner from '@/Components/ViewOnlyBanner';
import FooterCard from '@/Components/FooterCard';
import GlobalSearch, { SearchResult } from '@/Components/GlobalSearch';
import InlineImpactStatusPill from '@/Components/InlineImpactStatusPill';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import RecentActivityGrid, { RecentActivityTile } from '@/Components/RecentActivityGrid';
import RecentRegistrationsFeed, { RegistrationItem } from '@/Components/RecentRegistrationsFeed';
import SystemOverviewPanel, { SystemOverviewStats } from '@/Components/SystemOverviewPanel';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import DateRangeFilter from '@/Components/DateRangeFilter';
const OverviewAnalytics = lazy(() => import('@/Components/OverviewAnalytics'));

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

// Phase 06d.0 — per-KPI delta + sparkline type contracts.
type KpiDelta = { value: number; positiveIsGood?: boolean };
type KpiDeltas = Record<string, KpiDelta>;
type KpiSeries = Record<string, number[]>;

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
    /** Phase 06d.0 — per-KPI delta (current 7d vs prior 7d) — Admin variant only. */
    kpiDeltas?: KpiDeltas;
    /** Phase 06d.0 — per-KPI 14-day sparkline series — Admin variant only. */
    kpiSeries?: KpiSeries;
    /** Phase 06d.1 — 'today' | 'week' | 'month' | 'year' | 'custom' (URL-bound). */
    rangeKey?: string;
    rangeFrom?: string | null;
    rangeTo?: string | null;
    /** Phase 06d.1 — X-axis labels for the Overview Analytics chart (oldest → newest). */
    rangeLabels?: string[];
    /** Phase 06d.1 — per-metric cumulative counters scoped to the chosen date range. */
    chartSeries?: Record<string, number[]>;
    /** Phase 06d.2 — system stats payload (DB/Storage/Active Users/System Health). */
    systemOverview?: SystemOverviewStats;
    /** Phase 06d.2 — search index for the topbar Combobox. */
    globalSearchIndex?: SearchResult[];
    /** Phase 06d.2 — 6 tiles with counts + latest relative-time labels. */
    recentActivity?: RecentActivityTile[];
    /** Phase 06d.2 — 3 mixed-source registration cards sorted desc by createdAt. */
    recentRegistrations?: RegistrationItem[];
};

/** Phase 06d.0 — minimal auth user shape that the dashboard reads from usePage().props.auth.user.
 * Mirrors `HandleInertiaRequests::share()`'s auth shape; kept narrow to avoid coupling. */
type AuthLikeProps = {
    auth?: { user?: { id?: number; name?: string; email?: string } };
};

/**
 * Dashboard — Phase 06b polish + Phase 06d.0 admin variant.
 *
 * Selects both layout (admin-only sidebar) AND content variant by `props.variant`.
 *
 * Per-role variants:
 *  - officer / team / impactCell / zonal: AuthenticatedLayout (top nav only)
 *  - admin: AdminDashboardLayout (LEFT sidebar + top nav + footer skeleton)
 *
 * Admin variant (Phase 06d.0 ship):
 *  - 7 KPI cards with delta + sparkline + AnimatedCounter
 *  - Greeting block ("Good morning, Tunde")
 *  - Quick Actions row (4 cards)
 *  - 06d.2 will add System Overview + Recent Activity + Recent Registrations
 */
export default function Dashboard({
    variant,
    kpis,
    queue,
    recentSubmissions,
    zonalCells,
    zonalSubmissions,
    primaryCellId,
    activeRole,
    activeGroup,
    kpiDeltas,
    kpiSeries,
    rangeKey,
    rangeFrom,
    rangeTo,
    rangeLabels,
    chartSeries,
    systemOverview,
    globalSearchIndex,
    recentActivity,
    recentRegistrations,
}: DashboardPageProps) {
    // Phase 06d.0 — admin variant gets its own layout (sidebar + greeting).
    // Pull the authenticated user's name for the Greeting block (auth is shared by
    // HandleInertiaRequests::share() so it's available on every page props).
    const authName = (usePage() as any).props?.auth?.user?.name as string | undefined;
    if (variant === 'admin') {
        return (
            <AdminDashboardLayout
                header={<AdminHeader activeRole={activeRole} activeGroup={activeGroup} />}
                searchIndex={globalSearchIndex ?? []}
                footer={<FooterCard appName="Summit Bible Church" appEnv={import.meta.env.MODE ?? 'local'} appVersion="1.0.0" year={new Date().getFullYear()} />}
            >
                <Head title="Dashboard" />
                <ViewOnlyBanner role={activeRole} />
                <AdminDashboard
                    kpis={kpis as AdminKpis}
                    kpiDeltas={kpiDeltas ?? {}}
                    kpiSeries={kpiSeries ?? {}}
                    userName={authName}
                    rangeKey={rangeKey ?? 'week'}
                    rangeFrom={rangeFrom ?? null}
                    rangeTo={rangeTo ?? null}
                    rangeLabels={rangeLabels ?? []}
                    chartSeries={chartSeries ?? {}}
                    systemOverview={systemOverview}
                    recentActivity={recentActivity ?? []}
                    recentRegistrations={recentRegistrations ?? []}
                />
            </AdminDashboardLayout>
        );
    }

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

            <ViewOnlyBanner role={activeRole} />
            {variant === 'officer' ? (
                <OfficerDashboard kpis={kpis as OfficerKpis} queue={queue as QueueRow[]} />
            ) : variant === 'team' ? (
                <TeamDashboard kpis={kpis as TeamKpis} queue={queue as TeamQueueRow[]} activeRole={activeRole} />
            ) : variant === 'impactCell' ? (
                <LeaderDashboard kpis={kpis as LeaderKpis} recentSubmissions={recentSubmissions ?? []} primaryCellId={primaryCellId} />
            ) : variant === 'zonal' ? (
                <ZonalDashboard kpis={kpis as ZonalKpis} cells={zonalCells ?? []} submissions={zonalSubmissions ?? []} />
            ) : null}
        </AuthenticatedLayout>
    );
}

/* ───────────  Section helpers  ─────────── */

function SectionHeader({
    title,
    iconPath,
    count,
    action,
}: {
    title: string;
    iconPath: React.ReactNode;
    count?: number | string;
    action?: React.ReactNode;
}) {
    return (
        <div className="mb-4 flex items-center justify-between gap-3">
            <div className="flex items-center gap-3">
                <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5" aria-hidden="true">
                        {iconPath}
                    </svg>
                </span>
                <div>
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                        {title}
                    </h3>
                    {count !== undefined && (
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            {count} {typeof count === 'number' ? (count === 1 ? 'item' : 'items') : ''}
                        </p>
                    )}
                </div>
            </div>
            {action}
        </div>
    );
}

function PageCard({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return (
        <div className={`overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800 ${className}`}>
            {children}
        </div>
    );
}

/* ───────────  Per-role page headers  ─────────── */

function OfficerHeader({ activeRole }: { activeRole: string | null }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                Follow Up Officer
            </p>
            <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                Your personal KPIs and queue
            </h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Active role: <span className="font-mono">{activeRole ?? '—'}</span>
            </p>
        </div>
    );
}

function TeamHeader({ activeRole, activeGroup }: { activeRole: string | null; activeGroup: string | null }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                Follow Up Team
            </p>
            <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                Team-wide queue and KPIs
            </h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Active role: <span className="font-mono">{activeRole ?? '—'}</span> · group <span className="font-mono">{activeGroup ?? '—'}</span>
            </p>
        </div>
    );
}

function LeaderHeader({ activeRole, cellName }: { activeRole: string | null; cellName: string | undefined }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                Impact Cell Leader
            </p>
            <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                {cellName ?? 'No cell assigned'}
            </h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Active role: <span className="font-mono">{activeRole ?? '—'}</span> · Weekly submissions
            </p>
        </div>
    );
}

function ZonalHeader({ activeRole }: { activeRole: string | null }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                Zonal Coordinator
            </p>
            <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                Zone-wide overview
            </h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Active role: <span className="font-mono">{activeRole ?? '—'}</span>
            </p>
        </div>
    );
}

function AdminHeader({ activeRole, activeGroup }: { activeRole: string | null; activeGroup: string | null }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                Administrator
            </p>
            <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                Dashboard
            </h2>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Active role: <span className="font-mono">{activeRole ?? '—'}</span> · Full system access
            </p>
        </div>
    );
}

/* ───────────  Status helpers  ─────────── */

function FollowUpStatusPill({ status }: { status: string | null }) {
    if (status === null || status === '' || status === 'NOT CONTACTED') {
        return <StatusPill tone="warn" dot>Not Contacted</StatusPill>;
    }
    if (status === 'CONTACTED') {
        return <StatusPill tone="success" dot>Contacted</StatusPill>;
    }
    if (status === 'WRONG NUMBER') {
        return <StatusPill tone="danger" dot>Wrong Number</StatusPill>;
    }
    if (status === 'NOT REACHABLE') {
        return <StatusPill tone="danger" dot>Not Reachable</StatusPill>;
    }
    return <StatusPill tone="neutral" dot>{status}</StatusPill>;
}

function ContactedStatusPill({ status }: { status: string | null }) {
    if (status === null || status === '' || status === 'No' || status === 'Not Contacted') {
        return <StatusPill tone="warn" dot>Not Contacted</StatusPill>;
    }
    if (status === 'AvailableForVisit') {
        return <StatusPill tone="brand" dot>Available for Visit</StatusPill>;
    }
    if (status === 'Visited') {
        return <StatusPill tone="success" dot>Visited</StatusPill>;
    }
    return <StatusPill tone="neutral" dot>{status}</StatusPill>;
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
                onError: () => { e.target.value = currentStatus ?? ''; },
            },
        );
    };

    return (
        <select
            value={currentStatus ?? ''}
            onChange={handleChange}
            disabled={isViewOnly}
            className={`block w-full max-w-40 rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 ${
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

/* ───────────  Per-variant dashboards  ─────────── */

const queueIconPath = (
    <><path d="M3 4h13a3 3 0 0 1 3 3v3a3 3 0 0 1-3 3H3z" /><path d="M3 11h11a3 3 0 0 1 3 3v3a3 3 0 0 1-3 3H3z" /></>
);
const inboxIconPath = (
    <><path d="M22 12h-6l-2 3h-4l-2-3H2" /><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" /></>
);
const usersIconPath = (
    <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></>
);
const zapIconPath = (
    <><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" /></>
);
const fileIconPath = (
    <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="9" y1="13" x2="15" y2="13" /><line x1="9" y1="17" x2="15" y2="17" /></>
);

function OfficerDashboard({ kpis, queue }: { kpis: OfficerKpis; queue: QueueRow[] }) {
    return (
        <div className="space-y-8">
            <section className="motion-safe:animate-fade-in">
                <SectionHeader title="Personal KPIs" iconPath={zapIconPath} />
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    <KPICard accent="indigo"  caption="Pending Contacts" value={kpis.pendingContacts} trend="≤ pending outreach" />
                    <KPICard accent="emerald" caption="Total Calls"      value={kpis.totalCalls}      trend="guests contacted" />
                    <KPICard accent="emerald" caption="Visited"          value={kpis.visited}         trend="confirmed visits" />
                    <KPICard accent="amber"   caption="Pending Visit"    value={kpis.pendingVisit}    trend="available, awaiting visit" />
                    <KPICard accent="default" caption="Response Rate"    value={`${kpis.responseRate.toFixed(1)}%`} trend="visited ÷ total calls" />
                </div>
            </section>

            <section className="motion-safe:animate-fade-in">
                <SectionHeader
                    title="My Queue"
                    iconPath={queueIconPath}
                    count={queue.length}
                    action={
                        <Link
                            href={route('guests.index')}
                            className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            See all in My Guests →
                        </Link>
                    }
                />
                {queue.length === 0 ? (
                    <EmptyState
                        title="No guests assigned yet"
                        description="When an admin assigns a guest to you, they'll appear here, sorted by priority."
                        iconPath={inboxIconPath}
                    />
                ) : (
                    <PageCard>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Guest</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Phone</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Visited</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Added</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {queue.map((g) => (
                                        <tr key={g.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                            <td className="px-4 py-3 text-sm">
                                                <Link
                                                    href={route('guests.show', g.id)}
                                                    className="font-medium text-gray-900 transition-colors hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                                                >
                                                    {g.guestName}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{g.phone ?? '—'}</td>
                                            <td className="px-4 py-3"><ContactedStatusPill status={g.contactedStatus} /></td>
                                            <td className="px-4 py-3">
                                                {g.visited
                                                    ? <StatusPill tone="success" dot>Visited</StatusPill>
                                                    : <StatusPill tone="warn" dot>Pending</StatusPill>}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                {g.createdAt?.slice(0, 10) ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </PageCard>
                )}
            </section>
        </div>
    );
}

function TeamDashboard({ kpis, queue, activeRole }: { kpis: TeamKpis; queue: TeamQueueRow[]; activeRole: string | null }) {
    const isViewOnly = activeRole === 'Follow_UP_View_Only';

    return (
        <div className="space-y-8">
            <section className="motion-safe:animate-fade-in">
                <SectionHeader title="Team KPIs" iconPath={zapIconPath} />
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <KPICard accent="indigo"  caption="Pending Contacts" value={kpis.pendingContacts} trend="not yet contacted" />
                    <KPICard accent="emerald" caption="Contacted Today"  value={kpis.contactedToday}  trend="contact sections logged today" />
                    <KPICard accent="rose"    caption="Wrong Number"     value={kpis.wrongNumber}     trend="marked wrong number" />
                    <KPICard accent="amber"   caption="Not Reachable"    value={kpis.notReachable}    trend="could not be reached" />
                </div>
            </section>

            <section className="motion-safe:animate-fade-in">
                <SectionHeader
                    title="Team Queue"
                    iconPath={queueIconPath}
                    count={queue.length}
                    action={
                        <Link
                            href={route('guests.index')}
                            className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            See all guests →
                        </Link>
                    }
                />
                {queue.length === 0 ? (
                    <EmptyState
                        title="No guests in the queue"
                        description="When guests are added, they'll appear here sorted by priority."
                        iconPath={inboxIconPath}
                    />
                ) : (
                    <PageCard>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Guest</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Phone</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Officer</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Follow Up Status</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Latest Contact</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Updated</th>
                                        <th className="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {queue.map((g) => (
                                        <tr key={g.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                            <td className="px-4 py-3 text-sm">
                                                <Link
                                                    href={route('guests.show', g.id)}
                                                    className="font-medium text-gray-900 transition-colors hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                                                >
                                                    {g.guestName}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{g.phone ?? '—'}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{g.officerName ?? '—'}</td>
                                            <td className="px-4 py-3">
                                                {isViewOnly ? (
                                                    <FollowUpStatusPill status={g.followUpStatus} />
                                                ) : (
                                                    <InlineStatusSelect guestId={g.id} currentStatus={g.followUpStatus} isViewOnly={false} />
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{g.latestContact ?? '—'}</td>
                                            <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{g.updatedAt?.slice(0, 10) ?? '—'}</td>
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
                                                        className="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-100 dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-900/60"
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
                    </PageCard>
                )}
            </section>
        </div>
    );
}

const submissionTypeLabels: Record<string, string> = {
    member: 'Members Data',
    report: 'Cell Report',
    childbirth: 'Childbirth',
    soul: 'Soul',
};

function LeaderDashboard({
    kpis,
    recentSubmissions,
    primaryCellId,
    assignedGuests = [],
    canEditImpactStatus = false,
}: {
    kpis: LeaderKpis;
    recentSubmissions: RecentSubmission[];
    primaryCellId?: string | null;
    assignedGuests?: { id: string; guestName: string; phone: string | null; impactStatus: string | null; createdAt: string | null }[];
    canEditImpactStatus?: boolean;
}) {
    return (
        <div className="space-y-8">
            {primaryCellId && <LeadershipBoard cellId={primaryCellId} canView={true} />}

            <section className="motion-safe:animate-fade-in">
                <SectionHeader title="Cell Snapshot" iconPath={zapIconPath} />
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <KPICard accent="indigo"  caption="Cell"        value={kpis.cellName} trend={kpis.memberCount > 0 ? `${kpis.memberCount} members` : 'No members'} />
                    <KPICard accent="emerald" caption="Members"     value={kpis.memberCount} trend="registered in cell" />
                    <KPICard accent="amber"   caption="This Week"   value={kpis.weekSubmissions} trend="submissions this week" />
                    <KPICard accent="default" caption="Total"       value={kpis.totalSubmissions} trend="all submissions" />
                </div>
            </section>

            <section className="motion-safe:animate-fade-in">
                <SectionHeader title="Quick Submit" iconPath={zapIconPath} />
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {(['member', 'report', 'childbirth', 'soul'] as const).map((type) => (
                        <Link
                            key={type}
                            href={`/impact-submissions/create?type=${type}`}
                            className="group flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 bg-white px-4 py-6 text-sm font-semibold text-gray-700 transition-all hover:border-indigo-400 hover:bg-indigo-50/50 hover:text-indigo-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20 dark:hover:text-indigo-300"
                            data-testid={`quick-submit-${type}`}
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 transition-transform group-hover:scale-110" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            {submissionTypeLabels[type]}
                        </Link>
                    ))}
                </div>
            </section>

            <section className="motion-safe:animate-fade-in">
                <SectionHeader
                    title="Recent Submissions"
                    iconPath={fileIconPath}
                    count={recentSubmissions.length}
                    action={
                        <Link
                            href={route('impact-submissions.my-reports')}
                            className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            View all my reports →
                        </Link>
                    }
                />
                {recentSubmissions.length === 0 ? (
                    <EmptyState
                        title="No submissions yet"
                        description="Use the quick-submit cards above to log your first cell activity."
                        iconPath={fileIconPath}
                    />
                ) : (
                    <PageCard>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Type</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Cell</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Preview</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {recentSubmissions.map((s) => (
                                        <tr key={s.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{submissionTypeLabels[s.type] ?? s.type}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{s.cellName ?? '—'}</td>
                                            <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{s.preview}</td>
                                            <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{s.createdAt?.slice(0, 10) ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </PageCard>
                )}
            </section>

            <section className="motion-safe:animate-fade-in">
                <SectionHeader
                    title="Assigned Guests"
                    iconPath={usersIconPath}
                    count={assignedGuests.length}
                    action={
                        assignedGuests.length > 0 ? (
                            <Link
                                href={route('guests.index')}
                                className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                            >
                                View all →
                            </Link>
                        ) : null
                    }
                />
                {assignedGuests.length === 0 ? (
                    <EmptyState
                        title="No assigned guests yet"
                        description={primaryCellId
                            ? 'Guests whose nearest Impact Cell matches your primary cell will appear here.'
                            : 'Submit your first report to anchor your cell, then assigned guests will appear here.'}
                        iconPath={usersIconPath}
                    />
                ) : (
                    <PageCard>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-testid="assigned-guests-table">
                                <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Phone</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Impact Status</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {assignedGuests.map((g) => (
                                        <tr key={g.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40" data-testid={`assigned-guest-row-${g.id}`}>
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{g.guestName}</td>
                                            <td className="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{g.phone ?? '—'}</td>
                                            <td className="px-4 py-3 text-sm">
                                                <InlineImpactStatusPill
                                                    guestId={g.id}
                                                    current={g.impactStatus}
                                                    canEdit={canEditImpactStatus}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {canEditImpactStatus && (
                                                    <Link
                                                        href={route('guests.edit', g.id)}
                                                        className="text-xs font-medium text-indigo-600 transition-colors hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                        data-testid={`assigned-guest-edit-${g.id}`}
                                                    >
                                                        Edit →
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </PageCard>
                )}
            </section>
        </div>
    );
}

function ZonalDashboard({ kpis, cells, submissions }: { kpis: ZonalKpis; cells: ZonalCell[]; submissions: RecentSubmission[] }) {
    return (
        <div className="space-y-8">
            <section className="motion-safe:animate-fade-in">
                <SectionHeader title="Zone Snapshot" iconPath={zapIconPath} />
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <KPICard accent="indigo"  caption="Impact Cells"     value={kpis.totalCells}        trend="in your zone" />
                    <KPICard accent="emerald" caption="Total Submissions" value={kpis.totalSubmissions} trend="all types" />
                    <KPICard accent="amber"   caption="Pending Guests"    value={kpis.pendingGuests}    trend="not yet contacted" />
                    <KPICard accent="emerald" caption="Contacted Guests"  value={kpis.contactedGuests}  trend="follow-up made" />
                </div>
            </section>

            <section className="motion-safe:animate-fade-in">
                <SectionHeader title="Impact Cells" iconPath={usersIconPath} count={cells.length} />
                {cells.length === 0 ? (
                    <EmptyState
                        title="No cells assigned"
                        description="Cells assigned to your zone will appear here once your coordinator sets them up."
                        iconPath={usersIconPath}
                    />
                ) : (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {cells.map((c) => (
                            <Link
                                key={c.id}
                                href={route('impact-submissions.index')}
                                className="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-card transition-all hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-card-hover dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-500"
                                data-testid={`zonal-cell-${c.id}`}
                            >
                                <span className="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 text-indigo-500" aria-hidden="true">
                                        <path d="M3 3h18v18H3z" /><path d="M3 9h18M9 21V9" />
                                    </svg>
                                    {c.name}
                                </span>
                                {c.is_primary && <StatusPill tone="brand" dot>Primary</StatusPill>}
                            </Link>
                        ))}
                    </div>
                )}
            </section>

            <section className="motion-safe:animate-fade-in">
                <SectionHeader
                    title="Recent Submissions"
                    iconPath={fileIconPath}
                    count={submissions.length}
                    action={
                        <Link
                            href={route('impact-submissions.index')}
                            className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            View all →
                        </Link>
                    }
                />
                {submissions.length === 0 ? (
                    <EmptyState
                        title="No submissions yet"
                        description="Recent cell activity across your zone will show up here."
                        iconPath={fileIconPath}
                    />
                ) : (
                    <PageCard>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Type</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Cell</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Preview</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {submissions.map((s) => (
                                        <tr key={s.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{submissionTypeLabels[s.type] ?? s.type}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{s.cellName ?? '—'}</td>
                                            <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{s.preview}</td>
                                            <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{s.createdAt?.slice(0, 10) ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </PageCard>
                )}
            </section>
        </div>
    );
}

function QuickLinkCard({ href, label, iconPath }: { href: string; label: string; iconPath: React.ReactNode }) {
    return (
        <Link
            href={href}
            className="group flex items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 bg-white px-4 py-5 text-sm font-semibold text-gray-700 transition-all hover:-translate-y-0.5 hover:border-indigo-400 hover:bg-indigo-50/50 hover:text-indigo-700 hover:shadow-card-hover dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20 dark:hover:text-indigo-300"
            data-testid={`quick-link-${label.toLowerCase().replace(/\s+/g, '-')}`}
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 transition-transform group-hover:scale-110" aria-hidden="true">
                {iconPath}
            </svg>
            {label}
        </Link>
    );
}

/**
 * Phase 06d.0 — admin variant dashboard (canonical).
 *
 * Renders inside AdminDashboardLayout (sidebar already mounted).
 *  - Greeting block ("Good morning, Tunde")  — uses the auth user's name prop
 *  - 7-card row of premium KPIs with delta + sparkline + AnimatedCounter
 *  - Quick Actions row (4 cards — phase 06 polish retained)
 *  - 06d.2 will add System Overview + Recent Activity + Recent Registrations
 *    sections below the Quick Actions row.
 */
/**
 * Phase 06d.1 — Admin dashboard Overview Analytics section.
 *
 *  - DateRangeFilter is eager-mounted (no React.lazy) so the filter UI is
 *    interactive the moment the admin dashboard mounts.
 *  - OverviewAnalytics is React.lazy() so the recharts bundle (~150 kB)
 *    is held back until the chart panel needs it. The Suspense fallback
 *    uses the same fixed 320 px height as the chart canvas to prevent
 *    CLS on first render.
 */
function OverviewAnalyticsSection({
    rangeKey,
    rangeFrom,
    rangeTo,
    rangeLabels,
    chartSeries,
}: {
    rangeKey: string;
    rangeFrom: string | null;
    rangeTo: string | null;
    rangeLabels: string[];
    chartSeries: Record<string, number[]>;
}) {
    return (
        <section
            className="motion-safe:animate-fade-in space-y-4"
            data-testid="overview-analytics-root"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h3 className="text-sm font-semibold text-gray-900 dark:text-white">Overview Analytics</h3>
                <DateRangeFilter rangeKey={rangeKey} customFrom={rangeFrom} customTo={rangeTo} />
            </div>
            <Suspense
                fallback={
                    <div
                        data-testid="overview-analytics-skeleton"
                        className="min-h-[320px] animate-pulse rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
                    />
                }
            >
                <OverviewAnalytics series={chartSeries} labels={rangeLabels} />
            </Suspense>
        </section>
    );
}

function AdminDashboard({
    kpis,
    kpiDeltas = {},
    kpiSeries = {},
    userName,
    rangeKey,
    rangeFrom,
    rangeTo,
    rangeLabels,
    chartSeries,
    systemOverview,
    recentActivity = [],
    recentRegistrations = [],
}: {
    kpis: AdminKpis;
    kpiDeltas?: Record<string, { value: number; positiveIsGood?: boolean }>;
    kpiSeries?: Record<string, number[]>;
    userName?: string;
    rangeKey?: string;
    rangeFrom?: string | null;
    rangeTo?: string | null;
    rangeLabels?: string[];
    chartSeries?: Record<string, number[]>;
    systemOverview?: SystemOverviewStats;
    recentActivity?: RecentActivityTile[];
    recentRegistrations?: RegistrationItem[];
}) {
    return (
        <div className="space-y-8" data-testid="admin-dashboard-root">
            {/* Greeting — uses the actual logged-in admin's name from auth.user.name. */}
            <section className="motion-safe:animate-fade-in" data-testid="admin-greeting-section">
                <Greeting fullName={userName ?? 'Administrator'} activeRole="Administrator" />
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Here&rsquo;s the full picture across the Summit Bible church platform.
                </p>
            </section>

            {/* 7-card row of premium KPIs */}
            <section className="motion-safe:animate-fade-in" data-testid="admin-kpi-row">
                <SectionHeader title="At a Glance" iconPath={zapIconPath} />
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KPICard
                        accent="indigo"
                        caption="Total Guests"
                        value={kpis.totalGuests}
                        delta={kpiDeltas.totalGuests}
                        series={kpiSeries.totalGuests}
                        animateValue={true}
                        trend="all records"
                    />
                    <KPICard
                        accent="amber"
                        caption="Pending Contacts"
                        value={kpis.pendingContacts}
                        delta={kpiDeltas.pendingContacts}
                        series={kpiSeries.pendingContacts}
                        animateValue={true}
                        trend="not yet contacted"
                    />
                    <KPICard
                        accent="emerald"
                        caption="Total Calls"
                        value={kpis.totalCalls}
                        delta={kpiDeltas.totalCalls}
                        series={kpiSeries.totalCalls}
                        animateValue={true}
                        trend="guests contacted"
                    />
                    <KPICard
                        accent="emerald"
                        caption="Visited"
                        value={kpis.visited}
                        delta={kpiDeltas.visited}
                        series={kpiSeries.visited}
                        animateValue={true}
                        trend="confirmed visits"
                    />
                    <KPICard
                        accent="blue"
                        caption="Impact Cells"
                        value={kpis.totalCells}
                        delta={kpiDeltas.totalCells}
                        series={kpiSeries.totalCells}
                        animateValue={true}
                        trend="registered cells"
                    />
                    <KPICard
                        accent="default"
                        caption="Total Submissions"
                        value={kpis.totalSubmissions}
                        delta={kpiDeltas.totalSubmissions}
                        series={kpiSeries.totalSubmissions}
                        animateValue={true}
                        trend="across all forms"
                    />
                    <KPICard
                        accent="default"
                        caption="Total Users"
                        value={kpis.totalUsers}
                        delta={kpiDeltas.totalUsers}
                        series={kpiSeries.totalUsers}
                        animateValue={true}
                        trend="system accounts"
                    />
                </div>
            </section>

            {/* Quick Actions — 4 cards (Phase 06 polish preserved) */}
            <section className="motion-safe:animate-fade-in">
                <SectionHeader title="Quick Actions" iconPath={zapIconPath} />
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <QuickLinkCard
                        href={route('guests.index')}
                        label="Manage Guests"
                        iconPath={<><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></>}
                    />
                    <QuickLinkCard
                        href={route('impact-cells.index')}
                        label="Impact Cells"
                        iconPath={<><path d="M3 3h18v18H3z" /><path d="M3 9h18M9 21V9" /></>}
                    />
                    <QuickLinkCard
                        href={route('reports.index')}
                        label="View Reports"
                        iconPath={<><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /></>}
                    />
                    <QuickLinkCard
                        href={route('csv.import')}
                        label="CSV Import"
                        iconPath={<><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="17 8 12 3 7 8" /><line x1="12" y1="3" x2="12" y2="15" /></>}
                    />
                </div>
            </section>

            {/* Phase 06d.1 — DateRangeFilter (eager) + lazy-loaded OverviewAnalytics */}
            <OverviewAnalyticsSection
                rangeKey={rangeKey ?? 'week'}
                rangeFrom={rangeFrom ?? null}
                rangeTo={rangeTo ?? null}
                rangeLabels={rangeLabels ?? []}
                chartSeries={chartSeries ?? {}}
            />

            {/* Phase 06d.2 — System Overview + Recent Activity + Recent Registrations */}
            <SystemOverviewPanel
                stats={systemOverview ?? { dbSizeMb: 0, dbSizeLabel: '—', storageMb: 0, storageLabel: '—', activeUsers: 0, healthLabel: 'Healthy', healthTone: 'success' }}
            />
            <RecentActivityGrid tiles={recentActivity} />
            <RecentRegistrationsFeed items={recentRegistrations} />
        </div>
    );
}
