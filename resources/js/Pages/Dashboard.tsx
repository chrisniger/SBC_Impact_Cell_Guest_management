import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import DashboardCard from '@/Components/DashboardCard';
import DashboardSection from '@/Components/DashboardSection';
import DashboardTable, { Column } from '@/Components/DashboardTable';
import EmptyState from '@/Components/EmptyState';
import Greeting from '@/Components/Greeting';
import KPICard from '@/Components/KPICard';
import LeadershipBoard from '@/Components/LeadershipBoard';
import LeadershipRollupWidget, { LeadershipRollupItem } from '@/Components/LeadershipRollupWidget';
import StatusPill from '@/Components/StatusPill';
import ViewOnlyBanner from '@/Components/ViewOnlyBanner';
import FooterCard from '@/Components/FooterCard';
import GlobalSearch, { SearchResult } from '@/Components/GlobalSearch';
import InlineImpactStatusPill from '@/Components/InlineImpactStatusPill';
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

// Phase 09 — Impact Cell Administrator (cross-cell + cross-zonal supervisor).
type ImpactCellAdminKpis = {
    totalPrimaries: number;
    totalSubCells: number;
    crossGroupUsers: number;
    zonalCoordinators: number;
    totalSubmissions: number;
    weekSubmissions: number;
};

// Phase 09 — cross-group submission feed envelope.
type ImpactCellAdminSubmission = {
    id: string;
    type: string;
    cellName: string | null;
    preview: string;
    authorName: string | null;
    authorRole?: string | null;
    createdAt: string | null;
};

type ZonalCell = { id: string; name: string; is_primary: boolean };

/** Phase 34 — in-app announcement row (shared across every dashboard variant). */
type AnnouncementRow = {
    id: number;
    title: string;
    body: string;
    authorName: string;
    createdAt: string | null;
};

// Phase 06d.0 — per-KPI delta + sparkline type contracts.
type KpiDelta = { value: number; positiveIsGood?: boolean };
type KpiDeltas = Record<string, KpiDelta>;
type KpiSeries = Record<string, number[]>;

type DashboardPageProps = {
    variant: 'officer' | 'team' | 'impactCell' | 'impactCellAdmin' | 'zonal' | 'admin';
    kpis: OfficerKpis | TeamKpis | LeaderKpis | ImpactCellAdminKpis | AdminKpis | ZonalKpis | null;
    queue: QueueRow[] | TeamQueueRow[];
    recentSubmissions?: RecentSubmission[];
    assignedGuests?: { id: string; guestName: string; phone: string | null; impactStatus: string | null; createdAt: string | null }[];
    zonalCells?: ZonalCell[];
    zonalSubmissions?: RecentSubmission[];
    primaryCellId?: string | null;
    canEditImpactStatus?: boolean;
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
    /** Phase 08+ — admin-wide leadership tree rollup (one card per primary cell,
     *  computed via 3 bulk queries server-side). Admin variant only. */
    leadershipRollup?: LeadershipRollupItem[];
    /** Phase 09 — Impact_Cell_Admin variant only. Cross-group submissions feed. */
    recentCrossCellSubs?: ImpactCellAdminSubmission[];
    /** Phase 09 — Impact_Cell_Admin variant only. Zonal-coordinator submissions feed. */
    recentZonalSubs?: ImpactCellAdminSubmission[];
    /** Phase 34 — in-app announcements (Messages board). Rendered above every variant. */
    announcements?: AnnouncementRow[];
};

/** Phase 06d.0 — minimal auth user shape that the dashboard reads from usePage().props.auth.user.
 * Mirrors `HandleInertiaRequests::share()`'s auth shape; kept narrow to avoid coupling. */
type AuthLikeProps = {
    auth?: { user?: { id?: number; name?: string; email?: string } };
};

/**
 * Dashboard — Phase 06b polish + Phase 06d.0 admin variant, Phase 06e unified layout.
 *
 * Selects ONLY the content variant by `props.variant`. Every role uses the
 * SAME layout (`AdminDashboardLayout`) — there is no per-role layout branch
 * here. The legacy `AuthenticatedLayout` was retired in Phase 06e and its
 * chunk deleted in the layout-cleanup pass.
 *
 * Per-role variants (content body only):
 *  - officer:     OfficerDashboard  (Personal KPIs + My Queue)
 *  - team:        TeamDashboard     (Team KPIs + Team Queue with inline follow-up updates)
 *  - impactCell:  LeaderDashboard   (Cell Snapshot + Quick Submit + Recent Submissions + Assigned Guests)
 *  - zonal:       ZonalDashboard    (Zone Snapshot + Impact Cells grid + Recent Submissions)
 *  - admin:       AdminDashboard    (Greeting + 7-KPI row + Quick Actions + lazy OverviewAnalytics + System Overview + Recent Activity + Recent Registrations)
 *
 * Role-aware sidebar lives in `AdminSidebar` (Phase 06e). It renders 5
 * grouped sections (Administrator / Impact Cell Leader / Follow-Up Officer /
 * Follow-Up Team / Zonal Coordinator). Administrator sees all 5 with
 * non-owner sections rendered inert ("Coming soon", cursor-not-allowed);
 * every other role sees only the matching owner section. Phase 06f made
 * the sidebar mobile-responsive (off-canvas drawer + hamburger toggle);
 * Phase 06g added swipe-to-close + focus-trap + inert on the main column.
 *
 * Admin variant (Phase 06d.0 ship):
 *  - 7 KPI cards with delta + sparkline + AnimatedCounter
 *  - Greeting block ("Good morning, Tunde")
 *  - Quick Actions row (4 cards)
 *  - 06d.1 added DateRangeFilter + lazy OverviewAnalytics (recharts ~150 kB)
 *  - 06d.2 will add System Overview + Recent Activity + Recent Registrations
 *
 * Phase 13+ premium polish:
 *  - Per-role variants share 3 new composite components:
 *      `DashboardCard`, `DashboardSection`, `DashboardTable`.
 *  - Section headers refactored from emoji-circle icons to a sober
 *    typographic eyebrow + title pattern (Linear / Vercel style).
 *  - Tables redeploy with denser chrome (`px-3 py-2.5`), tinted row
 *    hover, and a subtle off-white thead that doesn't compete with data.
 *  - Quick-link and quick-submit cards dropped the dashed border +
 *    upward float hover in favor of a solid surface + subtle outer ring.
 *  - CrossCellFeed rebuilt as a log-style timeline (left rule + monospace
 *    timestamp in muted gray) instead of a table list.
 *
 * All testids, role-specific contracts, routes, and behaviors preserved
 * verbatim so existing verifier scripts in scripts/verify_phase*.php and
 * end-to-end coverage keep passing.
 */
export default function Dashboard({
    variant,
    kpis,
    queue,
    recentSubmissions,
    assignedGuests,
    zonalCells,
    zonalSubmissions,
    primaryCellId,
    canEditImpactStatus,
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
    leadershipRollup,
    recentCrossCellSubs,
    recentZonalSubs,
    announcements,
}: DashboardPageProps) {
    // Phase 06e — single shell for every role variant.
    // AdminDashboardLayout is the unified layout; the role-aware
    // <AdminSidebar> renders 5 grouped sections, showing all 5 with
    // non-owner sections inert when active role is Administrator, or
    // only the matching owner section otherwise.
    const authName = (usePage() as any).props?.auth?.user?.name as string | undefined;

    const pageHeader =
        variant === 'officer'         ? <OfficerHeader activeRole={activeRole} /> :
        variant === 'team'            ? <TeamHeader activeRole={activeRole} activeGroup={activeGroup} /> :
        variant === 'impactCell'      ? (
            <LeaderHeader
                activeRole={activeRole}
                cellName={(kpis as LeaderKpis)?.cellName}
                memberCount={(kpis as LeaderKpis)?.memberCount ?? 0}
                weekSubmissions={(kpis as LeaderKpis)?.weekSubmissions ?? 0}
                totalSubmissions={(kpis as LeaderKpis)?.totalSubmissions ?? 0}
            />
        ) :
        variant === 'impactCellAdmin' ? <ImpactCellAdminHeader activeRole={activeRole} /> :
        variant === 'zonal'           ? <ZonalHeader activeRole={activeRole} /> :
                                         <AdminHeader activeRole={activeRole} activeGroup={activeGroup} />;

    return (
        <AdminDashboardLayout
            header={pageHeader}
            searchIndex={globalSearchIndex ?? []}
            footer={
                <FooterCard
                    appName="Summit Bible Church"
                    appEnv={import.meta.env.MODE ?? 'local'}
                    appVersion="1.0.0"
                    year={new Date().getFullYear()}
                />
            }
        >
            <Head title="Dashboard" />
            <ViewOnlyBanner role={activeRole} />
            {/* Phase 34 — in-app announcements visible to every role. */}
            {announcements && announcements.length > 0 && <AnnouncementsBoard announcements={announcements} />}
            {variant === 'admin' ? (
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
                    leadershipRollup={leadershipRollup ?? []}
                />
            ) : variant === 'officer' ? (
                <OfficerDashboard kpis={kpis as OfficerKpis} queue={queue as QueueRow[]} />
            ) : variant === 'team' ? (
                <TeamDashboard kpis={kpis as TeamKpis} queue={queue as TeamQueueRow[]} activeRole={activeRole} />
            ) : variant === 'impactCell' ? (
                <LeaderDashboard
                    kpis={kpis as LeaderKpis}
                    recentSubmissions={recentSubmissions ?? []}
                    primaryCellId={primaryCellId}
                    assignedGuests={assignedGuests ?? []}
                    canEditImpactStatus={canEditImpactStatus ?? false}
                />
            ) : variant === 'impactCellAdmin' ? (
                <ImpactCellAdminDashboard
                    kpis={kpis as ImpactCellAdminKpis}
                    recentCrossCellSubs={recentCrossCellSubs ?? []}
                    recentZonalSubs={recentZonalSubs ?? []}
                    leadershipRollup={leadershipRollup ?? []}
                />
            ) : variant === 'zonal' ? (
                <ZonalDashboard kpis={kpis as ZonalKpis} cells={zonalCells ?? []} submissions={zonalSubmissions ?? []} />
            ) : (
                <OfficerDashboard kpis={kpis as OfficerKpis} queue={queue as QueueRow[]} />
            )}
        </AdminDashboardLayout>
    );
}

/* ───────────  Section helpers  ─────────── */

// PageCard kept as a compatibility alias — DashboardTable now owns the
// dashboard card chrome. This remains so any downstream component still
// importing the old name (or in-flight pages) keeps compiling.
function PageCard({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return (
        <div className={`motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800 ${className}`}>
            {children}
        </div>
    );
}

/* ───────────  Phase 34 — Announcements board (all roles)  ─────────── */

const announcementIconPath = (
    <><path d="M3 11l18-6v14L3 13v-2z" /><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6" /></>
);

function AnnouncementsBoard({ announcements }: { announcements: AnnouncementRow[] }) {
    return (
        <DashboardSection
            sectionTestId="announcements-board"
            eyebrow="Announcements"
            title="From the leadership team"
            description="Latest messages from the administrator — visible to everyone."
            icon={announcementIconPath}
            count={announcements.length}
        >
            <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                {announcements.slice(0, 4).map((a) => (
                    <DashboardCard
                        key={a.id}
                        dataCard={`announcement-${a.id}`}
                        className="px-5 py-4"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <h4 className="text-sm font-semibold text-gray-900 dark:text-white" data-testid={`announcement-title-${a.id}`}>
                                {a.title}
                            </h4>
                            <span className="shrink-0 text-[11px] tabular-nums text-gray-400 dark:text-gray-500">
                                {a.createdAt?.slice(0, 10) ?? ''}
                            </span>
                        </div>
                        <p className="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-gray-600 dark:text-gray-400" data-testid={`announcement-body-${a.id}`}>
                            {a.body}
                        </p>
                        <p className="mt-2 text-[11px] text-gray-400 dark:text-gray-500">
                            {a.authorName}
                        </p>
                    </DashboardCard>
                ))}
            </div>
        </DashboardSection>
    );
}

/* ───────────  Per-role page headers  ─────────── */

function OfficerHeader({ activeRole }: { activeRole: string | null }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Follow Up Officer
            </p>
            <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                Your personal KPIs and queue
            </h2>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Active role: <span className="font-mono text-gray-700 dark:text-gray-300">{activeRole ?? '—'}</span>
            </p>
        </div>
    );
}

function TeamHeader({ activeRole, activeGroup }: { activeRole: string | null; activeGroup: string | null }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Follow Up Team
            </p>
            <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                Team-wide queue and KPIs
            </h2>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Active role: <span className="font-mono text-gray-700 dark:text-gray-300">{activeRole ?? '—'}</span> · group <span className="font-mono text-gray-700 dark:text-gray-300">{activeGroup ?? '—'}</span>
            </p>
        </div>
    );
}

function LeaderHeader({
    activeRole,
    cellName,
    memberCount = 0,
    weekSubmissions = 0,
    totalSubmissions = 0,
}: {
    activeRole: string | null;
    cellName: string | undefined;
    memberCount?: number;
    weekSubmissions?: number;
    totalSubmissions?: number;
}) {
    // Phase 13+: rich welcome/status band — role chip + cell name + stats
    // strip + Submit Report CTA. Replaces the original sparse title+role
    // text per the Impact Cell Leader redesign brief.
    const hasCell = hasUsableCellName(cellName);
    return (
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between" data-testid="leader-page-header">
            <div className="min-w-0">
                <span
                    aria-label="Active role"
                    className="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                >
                    <span aria-hidden="true" className="inline-block h-1.5 w-1.5 rounded-full bg-indigo-500 dark:bg-indigo-300" />
                    Impact Cell Leader
                </span>
                <h2 className="mt-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    {hasCell ? cellName : 'Cell pending setup'}
                </h2>
                <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span>
                        Active role: <span className="font-mono text-gray-700 dark:text-gray-300">{activeRole ?? '—'}</span>
                    </span>
                    <span aria-hidden="true" className="text-gray-300 dark:text-gray-600">|</span>
                    <span>
                        <span data-testid="leader-header-members" className="font-semibold tabular-nums text-gray-700 dark:text-gray-300">{memberCount}</span>{' '}
                        {memberCount === 1 ? 'member' : 'members'}
                    </span>
                    <span aria-hidden="true" className="text-gray-300 dark:text-gray-600">|</span>
                    <span>
                        <span data-testid="leader-header-week" className="font-semibold tabular-nums text-gray-700 dark:text-gray-300">{weekSubmissions}</span>{' '}
                        submissions this week
                    </span>
                    <span aria-hidden="true" className="text-gray-300 dark:text-gray-600">|</span>
                    <span>
                        <span data-testid="leader-header-total" className="font-semibold tabular-nums text-gray-700 dark:text-gray-300">{totalSubmissions}</span>{' '}
                        total submissions
                    </span>
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                {hasCell ? (
                    <Link
                        href="/impact-submissions/create?type=report"
                        data-testid="leader-header-cta-submit-report"
                        className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="9" y1="13" x2="15" y2="13" />
                        </svg>
                        Submit Report
                    </Link>
                ) : (
                    <Link
                        href="/impact-submissions/create?type=report"
                        data-testid="leader-header-cta-anchor-cell"
                        className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        Anchor your cell
                    </Link>
                )}
            </div>
        </div>
    );
}

function ZonalHeader({ activeRole }: { activeRole: string | null }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Zonal Coordinator
            </p>
            <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                Zone-wide overview
            </h2>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Active role: <span className="font-mono text-gray-700 dark:text-gray-300">{activeRole ?? '—'}</span>
            </p>
        </div>
    );
}

function ImpactCellAdminHeader({ activeRole }: { activeRole: string | null }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Impact Cell Administrator
            </p>
            <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                Cross-cell & cross-zonal overview
            </h2>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Active role: <span className="font-mono text-gray-700 dark:text-gray-300">{activeRole ?? '—'}</span> · Supervisor scope: every primary cell + every zonal coordinator
            </p>
        </div>
    );
}

function AdminHeader({ activeRole, activeGroup }: { activeRole: string | null; activeGroup: string | null }) {
    return (
        <div>
            <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Administrator
            </p>
            <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                Dashboard
            </h2>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Active role: <span className="font-mono text-gray-700 dark:text-gray-300">{activeRole ?? '—'}</span> · Full system access
            </p>
        </div>
    );
}

function hasUsableCellName(cellName: string | null | undefined): cellName is string {
    const normalized = (cellName ?? '').trim();
    return normalized !== '' && normalized !== '-' && normalized !== '\u2014' && normalized !== 'â€”';
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

    // Phase 13+ premium polish — switched to a Notion-style inline property
    // select: pill-shaped chrome, no heavy outline, soft hover/focus ring.
    return (
        <div className={`relative inline-block ${isViewOnly ? 'opacity-60' : ''}`}>
            <select
                value={currentStatus ?? ''}
                onChange={handleChange}
                disabled={isViewOnly}
                title={isViewOnly ? 'View-only mode — cannot edit' : 'Update follow-up status'}
                data-testid={`inline-status-select-${guestId}`}
                className={`appearance-none rounded-full border border-gray-200 bg-gray-50 py-1 pl-3 pr-8 text-xs font-medium text-gray-700 shadow-none transition-colors hover:border-gray-300 hover:bg-white focus:border-indigo-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-800 dark:focus:border-indigo-400 dark:focus:bg-gray-800 ${
                    isViewOnly ? 'cursor-not-allowed' : 'cursor-pointer'
                }`}
            >
                <option value="">— Set status —</option>
                <option value="NOT CONTACTED">Not Contacted</option>
                <option value="CONTACTED">Contacted</option>
                <option value="WRONG NUMBER">Wrong Number</option>
                <option value="NOT REACHABLE">Not Reachable</option>
            </select>
            <span
                aria-hidden="true"
                className="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-400"
            >
                <svg viewBox="0 0 20 20" fill="currentColor" className="h-3.5 w-3.5">
                    <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                </svg>
            </span>
        </div>
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
    const columns: Column<QueueRow>[] = [
        {
            header: 'Guest',
            cell: (g) => (
                <Link
                    href={route('guests.show', g.id)}
                    className="font-medium text-gray-900 transition-colors hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                >
                    {g.guestName}
                </Link>
            ),
        },
        {
            header: 'Phone',
            cell: (g) => <span className="font-mono text-gray-700 dark:text-gray-300">{g.phone ?? '—'}</span>,
        },
        {
            header: 'Status',
            cell: (g) => <ContactedStatusPill status={g.contactedStatus} />,
        },
        {
            header: 'Visited',
            cell: (g) => g.visited
                ? <StatusPill tone="success" dot>Visited</StatusPill>
                : <StatusPill tone="warn" dot>Pending</StatusPill>,
        },
        {
            header: 'Added',
            headerClassName: 'text-right',
            cell: (g) => (
                <span className="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                    {g.createdAt?.slice(0, 10) ?? '—'}
                </span>
            ),
            align: 'right',
            cellClassName: 'text-right',
        },
    ];

    return (
        <div className="space-y-8">
            <DashboardSection
                eyebrow="Personal KPIs"
                title="Your portfolio at a glance"
                description="Outreach progress, response rate, and queue volume for the guests assigned to you."
                icon={zapIconPath}
            >
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    <KPICard accent="indigo"  caption="Pending Contacts" value={kpis.pendingContacts} trend="≤ pending outreach" />
                    <KPICard accent="emerald" caption="Total Calls"      value={kpis.totalCalls}      trend="guests contacted" />
                    <KPICard accent="emerald" caption="Visited"          value={kpis.visited}         trend="confirmed visits" />
                    <KPICard accent="amber"   caption="Pending Visit"    value={kpis.pendingVisit}    trend="available, awaiting visit" />
                    <KPICard accent="default" caption="Response Rate"    value={`${kpis.responseRate.toFixed(1)}%`} trend="visited ÷ total calls" />
                </div>
            </DashboardSection>

            <DashboardSection
                eyebrow="My Queue"
                title="Assigned guests to follow up"
                icon={queueIconPath}
                count={queue.length === 0 ? 'Empty' : queue.length}
                action={
                    <Link
                        href={route('guests.index')}
                        className="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600"
                    >
                        See all in My Guests
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </Link>
                }
            >
                <DashboardTable<QueueRow>
                    rows={queue}
                    columns={columns}
                    tableTestId="officer-queue-table"
                    rowTestId={(g) => `officer-queue-row-${g.id}`}
                    emptyTitle="No guests assigned yet"
                    emptyDescription="When an admin assigns a guest to you, they'll appear here, sorted by priority."
                    emptyIconPath={inboxIconPath}
                />
            </DashboardSection>
        </div>
    );
}

function TeamDashboard({ kpis, queue, activeRole }: { kpis: TeamKpis; queue: TeamQueueRow[]; activeRole: string | null }) {
    const isViewOnly = activeRole === 'Follow_UP_View_Only';

    const columns: Column<TeamQueueRow>[] = [
        {
            header: 'Guest',
            cell: (g) => (
                <Link
                    href={route('guests.show', g.id)}
                    className="font-medium text-gray-900 transition-colors hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                >
                    {g.guestName}
                </Link>
            ),
        },
        {
            header: 'Phone',
            cell: (g) => <span className="font-mono text-gray-700 dark:text-gray-300">{g.phone ?? '—'}</span>,
        },
        {
            header: 'Officer',
            cell: (g) => <span className="text-sm text-gray-700 dark:text-gray-300">{g.officerName ?? '—'}</span>,
        },
        {
            header: 'Follow Up Status',
            cell: (g) => isViewOnly
                ? <FollowUpStatusPill status={g.followUpStatus} />
                : <InlineStatusSelect guestId={g.id} currentStatus={g.followUpStatus} isViewOnly={false} />,
        },
        {
            header: 'Latest Contact',
            cell: (g) => <span className="text-xs text-gray-500 dark:text-gray-400 tabular-nums">{g.latestContact ?? '—'}</span>,
        },
        {
            header: 'Updated',
            cell: (g) => <span className="text-xs text-gray-500 dark:text-gray-400 tabular-nums">{g.updatedAt?.slice(0, 10) ?? '—'}</span>,
        },
        {
            header: '',
            align: 'right',
            cell: (g) => !isViewOnly && g.followUpStatus !== 'CONTACTED' ? (
                <button
                    type="button"
                    onClick={() => {
                        router.patch(
                            route('guests.follow-up-status', g.id),
                            { follow_up_status: 'CONTACTED' },
                            { preserveScroll: true, preserveState: true },
                        );
                    }}
                    data-testid={`mark-contacted-${g.id}`}
                    className="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 transition-colors hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-800/60 dark:bg-emerald-900/30 dark:text-emerald-300 dark:hover:bg-emerald-900/50"
                >
                    Mark Contacted
                </button>
            ) : null,
        },
    ];

    return (
        <div className="space-y-8">
            <DashboardSection
                eyebrow="Team KPIs"
                title="Team-wide progress"
                description="Operational metrics across the follow-up team — outreach velocity, completion, and exceptions."
                icon={zapIconPath}
            >
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <KPICard accent="indigo"  caption="Pending Contacts" value={kpis.pendingContacts} trend="not yet contacted" />
                    <KPICard accent="emerald" caption="Contacted Today"  value={kpis.contactedToday}  trend="contact sections logged today" />
                    <KPICard accent="rose"    caption="Wrong Number"     value={kpis.wrongNumber}     trend="marked wrong number" />
                    <KPICard accent="amber"   caption="Not Reachable"    value={kpis.notReachable}    trend="could not be reached" />
                </div>
            </DashboardSection>

            <DashboardSection
                eyebrow="Team Queue"
                title="All assigned guests — operational queue"
                description={isViewOnly
                    ? 'View-only mode active. Status changes are disabled.'
                    : 'Click the status pill to update follow-up state in place. Changes propagate to the guest record immediately.'}
                icon={queueIconPath}
                count={queue.length === 0 ? 'Empty' : queue.length}
                action={
                    <Link
                        href={route('guests.index')}
                        className="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600"
                    >
                        See all guests
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </Link>
                }
            >
                <DashboardTable<TeamQueueRow>
                    rows={queue}
                    columns={columns}
                    tableTestId="team-queue-table"
                    rowTestId={(g) => `team-queue-row-${g.id}`}
                    emptyTitle="No guests in the queue"
                    emptyDescription="When guests are added, they'll appear here sorted by priority."
                    emptyIconPath={inboxIconPath}
                    compact
                />
            </DashboardSection>
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
    const hasCell = hasUsableCellName(kpis.cellName);

    const subColumns: Column<RecentSubmission>[] = [
        {
            header: 'Type',
            cell: (s) => <span className="font-medium text-gray-900 dark:text-gray-100">{submissionTypeLabels[s.type] ?? s.type}</span>,
        },
        { header: 'Cell', cell: (s) => <span className="text-sm text-gray-700 dark:text-gray-300">{s.cellName ?? '—'}</span> },
        { header: 'Preview', cell: (s) => <span className="text-sm text-gray-600 dark:text-gray-400">{s.preview}</span> },
        {
            header: 'Date',
            align: 'right',
            cell: (s) => <span className="text-xs tabular-nums text-gray-500 dark:text-gray-400">{s.createdAt?.slice(0, 10) ?? '—'}</span>,
        },
    ];

    const guestColumns: Column<{ id: string; guestName: string; phone: string | null; impactStatus: string | null; createdAt: string | null }>[] = [
        { header: 'Name', cell: (g) => <span className="font-medium text-gray-900 dark:text-gray-100">{g.guestName}</span> },
        { header: 'Phone', cell: (g) => <span className="font-mono text-gray-700 dark:text-gray-300">{g.phone ?? '—'}</span> },
        {
            header: 'Impact Status',
            cell: (g) => (
                <InlineImpactStatusPill guestId={g.id} current={g.impactStatus} canEdit={canEditImpactStatus} />
            ),
        },
        {
            header: '',
            align: 'right',
            cell: (g) => canEditImpactStatus ? (
                <Link
                    href={route('guests.edit', g.id)}
                    className="text-xs font-medium text-indigo-600 transition-colors hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                    data-testid={`assigned-guest-edit-${g.id}`}
                >
                    Edit →
                </Link>
            ) : null,
        },
    ];

    return (
        <div className="space-y-8">
            {primaryCellId && (
                <DashboardSection
                    sectionTestId="leader-board-section"
                    eyebrow="Leadership"
                    title="Your leadership tree"
                    description="Engagement status across each sub-cell under your primary cell."
                    icon={usersIconPath}
                    count={primaryCellId}
                    action={
                        <Link
                            href={route('leadership.index')}
                            className="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600"
                        >
                            View all boards
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                                <path d="M5 12h14M12 5l7 7-7 7" />
                            </svg>
                        </Link>
                    }
                >
                    <LeadershipBoard cellId={primaryCellId} canView={true} />
                </DashboardSection>
            )}

            <DashboardSection
                eyebrow="Cell Snapshot"
                title={hasCell ? "Your cell in numbers" : "Set up your cell to begin"}
                description={hasCell
                    ? "At-a-glance totals for membership, weekly activity, and all-time submissions."
                    : "Submit your first cell report from the quick-submit cards below to anchor your cell, or ask an administrator to assign you to one."}
                icon={zapIconPath}
            >
                {hasCell ? (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <KPICard accent="indigo"  caption="Cell"        value={kpis.cellName} trend={kpis.memberCount > 0 ? `${kpis.memberCount} members` : 'No members'} />
                        <KPICard accent="emerald" caption="Members"     value={kpis.memberCount} trend="registered in cell" />
                        <KPICard accent="amber"   caption="This Week"   value={kpis.weekSubmissions} trend="submissions this week" />
                        <KPICard accent="default" caption="Total"       value={kpis.totalSubmissions} trend="all submissions" />
                    </div>
                ) : (
                    <DashboardCard accent="indigo" className="px-5 py-5" dataCard="leader-cell-pending">
                        <div className="flex items-start gap-4">
                            <span
                                aria-hidden="true"
                                className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5">
                                    <path d="M3 3h18v18H3z" />
                                    <path d="M3 9h18M9 21V9" />
                                </svg>
                            </span>
                            <div className="min-w-0">
                                <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                                    Cell pending setup
                                </h3>
                                <p className="mt-1 text-sm leading-snug text-gray-600 dark:text-gray-400">
                                    Your Impact Cell Leader role is active but no primary cell is linked to your account yet. Submit a Cell Report to anchor one, or reach out to an administrator to assign you to an existing cell.
                                </p>
                                <div className="mt-3">
                                    <Link
                                        href="/impact-submissions/create?type=report"
                                        data-testid="leader-cell-pending-cta"
                                        className="inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition-colors hover:border-indigo-300 hover:bg-indigo-100 dark:border-indigo-800/60 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50"
                                    >
                                        Anchor your cell →
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </DashboardCard>
                )}
            </DashboardSection>

            <DashboardSection
                eyebrow="Quick Submit"
                title="Log a new submission"
                description="Direct entry points into the four submission types — submissions land under your primary cell."
                icon={zapIconPath}
            >
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {([
                        {
                            type: 'member',
                            label: 'Members Data',
                            description: 'Register a new cell member',
                            iconPath: (
                                <>
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </>
                            ),
                            accentBg: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300',
                        },
                        {
                            type: 'report',
                            label: 'Cell Report',
                            description: 'Weekly report and attendance',
                            iconPath: (
                                <>
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                    <line x1="9" y1="13" x2="15" y2="13" />
                                </>
                            ),
                            accentBg: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300',
                        },
                        {
                            type: 'childbirth',
                            label: 'Childbirth',
                            description: 'Record a new birth in the cell',
                            iconPath: (
                                <>
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                                </>
                            ),
                            accentBg: 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300',
                        },
                        {
                            type: 'soul',
                            label: 'Soul Registration',
                            description: 'Add a soul won this week',
                            iconPath: (
                                <>
                                    <circle cx="12" cy="8" r="4" />
                                    <path d="M4 21a8 8 0 0 1 16 0" />
                                </>
                            ),
                            accentBg: 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-300',
                        },
                    ] as const).map((tile) => (
                        <Link
                            key={tile.type}
                            href={`/impact-submissions/create?type=${tile.type}`}
                            data-testid={`quick-submit-${tile.type}`}
                            className="group flex flex-col items-start gap-2 rounded-xl border border-gray-200 bg-white p-4 text-left shadow-card transition-all duration-200 hover:border-indigo-400 hover:bg-indigo-50/40 hover:shadow-card-hover hover:ring-1 hover:ring-indigo-200/60 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20 dark:hover:ring-indigo-700/40"
                        >
                            <span
                                aria-hidden="true"
                                className={`inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${tile.accentBg} transition-transform group-hover:scale-110`}
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    {tile.iconPath}
                                </svg>
                            </span>
                            <span className="text-sm font-semibold leading-tight text-gray-900 transition-colors group-hover:text-indigo-700 dark:text-white dark:group-hover:text-indigo-300">
                                {tile.label}
                            </span>
                            <span className="text-xs leading-snug text-gray-500 dark:text-gray-400">
                                {tile.description}
                            </span>
                        </Link>
                    ))}
                </div>
            </DashboardSection>

            <DashboardSection
                eyebrow="Recent Submissions"
                title="Latest cell submissions"
                icon={fileIconPath}
                count={recentSubmissions.length}
                action={
                    <Link
                        href={route('impact-submissions.my-reports')}
                        className="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600"
                    >
                        View all my submissions
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </Link>
                }
            >
                <DashboardTable<RecentSubmission>
                    rows={recentSubmissions}
                    columns={subColumns}
                    tableTestId="leader-recent-submissions-table"
                    rowTestId={(s) => `leader-recent-submission-row-${s.id}`}
                    emptyTitle="No submissions yet"
                    emptyDescription="Use the quick-submit cards above to log your first cell activity."
                    emptyIconPath={fileIconPath}
                />
            </DashboardSection>

            <DashboardSection
                eyebrow="Assigned Guests"
                title="Guests whose nearest cell matches yours"
                description={canEditImpactStatus
                    ? 'Tap the pill to update the impact status in place. Edits propagate to the guest record immediately.'
                    : 'Impact-status edits are disabled for this view. Contact a leader with edit permissions to update.'}
                icon={usersIconPath}
                count={assignedGuests.length === 0 ? 'Empty' : assignedGuests.length}
                action={assignedGuests.length > 0 ? (
                    <Link
                        href={route('guests.index')}
                        className="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600"
                    >
                        View all
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </Link>
                ) : null}
            >
                <DashboardTable
                    rows={assignedGuests}
                    columns={guestColumns}
                    tableTestId="assigned-guests-table"
                    rowTestId={(g) => `assigned-guest-row-${g.id}`}
                    emptyTitle="No assigned guests yet"
                    emptyDescription={primaryCellId
                        ? 'Guests whose nearest Impact Cell matches your primary cell will appear here.'
                        : 'Submit your first report to anchor your cell, then assigned guests will appear here.'}
                    emptyIconPath={usersIconPath}
                />
            </DashboardSection>
        </div>
    );
}

function ZonalDashboard({ kpis, cells, submissions }: { kpis: ZonalKpis; cells: ZonalCell[]; submissions: RecentSubmission[] }) {
    const subColumns: Column<RecentSubmission>[] = [
        { header: 'Type', cell: (s) => <span className="font-medium text-gray-900 dark:text-gray-100">{submissionTypeLabels[s.type] ?? s.type}</span> },
        { header: 'Cell', cell: (s) => <span className="text-sm text-gray-700 dark:text-gray-300">{s.cellName ?? '—'}</span> },
        { header: 'Preview', cell: (s) => <span className="text-sm text-gray-600 dark:text-gray-400">{s.preview}</span> },
        {
            header: 'Date',
            align: 'right',
            cell: (s) => <span className="text-xs tabular-nums text-gray-500 dark:text-gray-400">{s.createdAt?.slice(0, 10) ?? '—'}</span>,
        },
    ];

    return (
        <div className="space-y-8">
            <DashboardSection
                eyebrow="Zone Snapshot"
                title="Your zone in numbers"
                description="Cells, submissions, and follow-up volume for the cells under your supervision."
                icon={zapIconPath}
            >
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <KPICard accent="indigo"  caption="Impact Cells"      value={kpis.totalCells}        trend="in your zone" />
                    <KPICard accent="emerald" caption="Total Submissions" value={kpis.totalSubmissions}  trend="all types" />
                    <KPICard accent="amber"   caption="Pending Guests"    value={kpis.pendingGuests}     trend="not yet contacted" />
                    <KPICard accent="emerald" caption="Contacted Guests"  value={kpis.contactedGuests}   trend="follow-up made" />
                </div>
            </DashboardSection>

            <DashboardSection
                eyebrow="Impact Cells"
                title="Cells in your zone"
                description="Tap any cell to drill into its submissions feed."
                icon={usersIconPath}
                count={cells.length === 0 ? 'Empty' : cells.length}
            >
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
                                className="group flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-card transition-all duration-200 hover:border-indigo-300 hover:bg-indigo-50/30 hover:shadow-card-hover hover:ring-1 hover:ring-indigo-200/60 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20 dark:hover:ring-indigo-700/40"
                                data-testid={`zonal-cell-${c.id}`}
                            >
                                <span className="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 text-indigo-500 transition-transform group-hover:scale-110" aria-hidden="true">
                                        <path d="M3 3h18v18H3z" /><path d="M3 9h18M9 21V9" />
                                    </svg>
                                    {c.name}
                                </span>
                                {c.is_primary
                                    ? <StatusPill tone="brand" dot>Primary</StatusPill>
                                    : <StatusPill tone="neutral" dot>Sub-cell</StatusPill>}
                            </Link>
                        ))}
                    </div>
                )}
            </DashboardSection>

            <DashboardSection
                eyebrow="Recent Submissions"
                title="Latest zone activity"
                icon={fileIconPath}
                count={submissions.length === 0 ? 'Empty' : submissions.length}
                action={
                    <Link
                        href={route('impact-submissions.index')}
                        className="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 transition-colors hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600"
                    >
                        View all
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </Link>
                }
            >
                <DashboardTable<RecentSubmission>
                    rows={submissions}
                    columns={subColumns}
                    tableTestId="zonal-submissions-table"
                    rowTestId={(s) => `zonal-submission-row-${s.id}`}
                    emptyTitle="No submissions yet"
                    emptyDescription="Recent cell activity across your zone will show up here."
                    emptyIconPath={fileIconPath}
                />
            </DashboardSection>
        </div>
    );
}

/**
 * Phase 09 — Impact Cell Administrator dashboard (cross-cell + cross-zonal supervisor).
 *
 * Re-uses the <LeadershipRollupWidget> the admin variant renders, plus two
 * cross-group submission feeds (mixed-source for the whole impactCell group +
 * a narrowed zonal-coordinator feed for separate audit visibility).
 *
 * Intentionally does NOT render the AdminDashboard's chart / KPI grid /
 * SystemOverview / Recent Activity tiles — those assume Follow-Up officer and
 * Guest data which is OUT of scope for a Phase 09 supervisor (only impactCell
 * + zonal activities per the spec). Supervisor scope is narrower on purpose.
 */
function ImpactCellAdminDashboard({
    kpis,
    recentCrossCellSubs,
    recentZonalSubs,
    leadershipRollup,
}: {
    kpis: ImpactCellAdminKpis;
    recentCrossCellSubs: ImpactCellAdminSubmission[];
    recentZonalSubs: ImpactCellAdminSubmission[];
    leadershipRollup: LeadershipRollupItem[];
}) {
    return (
        <div className="space-y-8" data-testid="impact-cell-admin-dashboard-root">
            <DashboardSection
                sectionTestId="impact-cell-admin-kpi-row"
                eyebrow="Supervisor Snapshot"
                title="Cross-cell & cross-zonal scope"
                description="A consolidated view across every primary cell and every zonal coordinator for audit and oversight."
                icon={zapIconPath}
            >
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <KPICard accent="indigo"  caption="Primary Cells"       value={kpis.totalPrimaries}    trend="registered primaries" />
                    <KPICard accent="emerald" caption="Sub-cells"           value={kpis.totalSubCells}     trend="across all primaries" />
                    <KPICard accent="blue"    caption="Cell Group Users"    value={kpis.crossGroupUsers}   trend="leaders, admins, zonal" />
                    <KPICard accent="amber"   caption="Zonal Coordinators"  value={kpis.zonalCoordinators} trend="across the system" />
                    <KPICard accent="default" caption="Total Submissions"  value={kpis.totalSubmissions}  trend="all-time, cross-cell" />
                    <KPICard accent="default" caption="This Week"           value={kpis.weekSubmissions}   trend="last 7 days" />
                </div>
            </DashboardSection>

            {/* Cross-cell leadership rollup — same widget the admin sees. */}
            <LeadershipRollupWidget items={leadershipRollup} />

            <DashboardSection
                sectionTestId="impact-cell-admin-feeds"
                eyebrow="Cross-Group Activity"
                title="Mixed-source submissions feed"
                description="Two parallel feeds (mixed-author + zonal-narrowed) for audit visibility."
                icon={fileIconPath}
            >
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <CrossCellFeed
                        title="All Cross-Cell Submissions"
                        subtitle="Authors: any user whose role is in Impact Cell group"
                        rows={recentCrossCellSubs}
                    />
                    <CrossCellFeed
                        title="Zonal Coordinator Activity"
                        subtitle="Authors: Impact_Zonal_Coordinator only"
                        rows={recentZonalSubs}
                        hideAuthorRole
                    />
                </div>
            </DashboardSection>
        </div>
    );
}

function CrossCellFeed({
    title,
    subtitle,
    rows,
    hideAuthorRole,
}: {
    title: string;
    subtitle: string;
    rows: ImpactCellAdminSubmission[];
    hideAuthorRole?: boolean;
}) {
    return (
        <DashboardCard dataCard="cross-cell-feed" className="flex flex-col">
            <div className="border-b border-gray-100 px-4 py-3 dark:border-gray-700/60">
                <h4 className="text-sm font-semibold text-gray-900 dark:text-white">{title}</h4>
                <p className="text-xs text-gray-500 dark:text-gray-400">{subtitle}</p>
            </div>
            {rows.length === 0 ? (
                <EmptyState
                    title="No recent activity"
                    iconPath={fileIconPath}
                />
            ) : (
                <ul
                    className="divide-y divide-gray-100 dark:divide-gray-700/60"
                    data-testid={`cross-cell-feed-${title.toLowerCase().replace(/\s+/g, '-')}`}
                >
                    {rows.map((row) => (
                        <li key={row.id} className="group flex items-start gap-3 px-4 py-3 transition-colors hover:bg-indigo-50/30 dark:hover:bg-gray-700/30">
                            <span
                                aria-hidden="true"
                                className="mt-1 inline-block h-2 w-2 shrink-0 rounded-full bg-indigo-400 group-hover:bg-indigo-500 dark:bg-indigo-500"
                            />
                            <div className="min-w-0 flex-1">
                                <Link
                                    href={route('impact-submissions.show', row.id)}
                                    className="text-sm font-medium text-gray-900 transition-colors hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                                >
                                    {submissionTypeLabels[row.type] ?? row.type}: {row.preview}
                                </Link>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    {row.cellName ? <>Cell: <span className="font-medium text-gray-700 dark:text-gray-300">{row.cellName}</span> · </> : null}
                                    {row.authorName ? <>by {row.authorName}</> : <>—</>}
                                    {!hideAuthorRole && row.authorRole ? (
                                        <> · <span className="font-mono text-[10px] text-gray-400">{row.authorRole}</span></>
                                    ) : null}
                                </p>
                            </div>
                            <span className="shrink-0 font-mono text-[11px] tabular-nums text-gray-400 dark:text-gray-500">
                                {row.createdAt?.slice(0, 10) ?? '—'}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardCard>
    );
}

function QuickLinkCard({ href, label, iconPath }: { href: string; label: string; iconPath: React.ReactNode }) {
    // Phase 13+ premium polish — replaced dashed border + float-on-hover with
    // a solid surface, soft hover ring, and a subtle border wash. The dashed
    // border previously signalled "empty / dropzone" — wrong for actionable UI.
    return (
        <Link
            href={href}
            className="group flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-5 text-sm font-semibold text-gray-700 shadow-card transition-all duration-200 hover:border-indigo-400 hover:bg-indigo-50/40 hover:text-indigo-700 hover:shadow-card-hover hover:ring-1 hover:ring-indigo-200/60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20 dark:hover:text-indigo-300 dark:hover:ring-indigo-700/40"
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
                <div>
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Trend
                    </p>
                    <h3 className="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                        Overview Analytics
                    </h3>
                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Track how guests, contacts, submissions, and users evolve over time.
                    </p>
                </div>
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
    leadershipRollup = [],
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
    leadershipRollup?: LeadershipRollupItem[];
}) {
    return (
        <div className="space-y-8" data-testid="admin-dashboard-root">
            <section className="motion-safe:animate-fade-in" data-testid="admin-greeting-section">
                <Greeting fullName={userName ?? 'Administrator'} activeRole="Administrator" />
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Here&rsquo;s the full picture across the Summit Bible church platform.
                </p>
            </section>

            <DashboardSection
                sectionTestId="admin-kpi-row"
                eyebrow="At a Glance"
                title="Key indicators"
                description="Headline metrics across the platform. Each delta compares the last 7 days against the prior 7."
                icon={zapIconPath}
            >
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
            </DashboardSection>

            <DashboardSection
                eyebrow="Quick Actions"
                title="Jump straight to a workspace"
                description="Common admin actions — guests, cells, reports, and bulk CSV import."
                icon={zapIconPath}
            >
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
            </DashboardSection>

            <OverviewAnalyticsSection
                rangeKey={rangeKey ?? 'week'}
                rangeFrom={rangeFrom ?? null}
                rangeTo={rangeTo ?? null}
                rangeLabels={rangeLabels ?? []}
                chartSeries={chartSeries ?? {}}
            />

            <LeadershipRollupWidget items={leadershipRollup} />

            <SystemOverviewPanel
                stats={systemOverview ?? { dbSizeMb: 0, dbSizeLabel: '—', storageMb: 0, storageLabel: '—', activeUsers: 0, healthLabel: 'Healthy', healthTone: 'success' }}
            />
            <RecentActivityGrid tiles={recentActivity} />
            <RecentRegistrationsFeed items={recentRegistrations} />
        </div>
    );
}
