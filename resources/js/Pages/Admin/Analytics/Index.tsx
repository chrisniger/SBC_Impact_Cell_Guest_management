import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import DashboardSection from '@/Components/DashboardSection';
import DateRangeFilter from '@/Components/DateRangeFilter';
import KPICard from '@/Components/KPICard';
import SystemOverviewPanel, { SystemOverviewStats } from '@/Components/SystemOverviewPanel';
import { Head, usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';

const OverviewAnalytics = lazy(() => import('@/Components/OverviewAnalytics'));

/**
 * Phase 34 — Admin Analytics page.
 *
 * Replaces the Phase 06d.0 "Coming soon" stub with the cross-cell trends
 * overview the stub promised: a recharts AreaChart (OverviewAnalytics)
 * with Today/Week/Month/Year/Custom range filter, a KPI delta row, and
 * the System Overview panel.
 *
 * The chart math comes from the shared App\Support\AnalyticsService (the
 * same numbers the Admin Dashboard renders) via Admin\AnalyticsController.
 */

type KpiDelta = { value: number; positiveIsGood?: boolean };

const zapIconPath = (
    <><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" /></>
);
const chartIconPath = (
    <><path d="M3 3v18h18" /><path d="M7 14l3-3 4 4 7-7" /></>
);

export default function AdminAnalyticsIndex() {
    const { props } = usePage<any>();

    const kpis: Record<string, number> = props.kpis ?? {};
    const kpiDeltas: Record<string, KpiDelta> = props.kpiDeltas ?? {};
    const kpiSeries: Record<string, number[]> = props.kpiSeries ?? {};
    const rangeKey: string = props.rangeKey ?? 'week';
    const rangeFrom: string | null = props.rangeFrom ?? null;
    const rangeTo: string | null = props.rangeTo ?? null;
    const rangeLabels: string[] = props.rangeLabels ?? [];
    const chartSeries: Record<string, number[]> = props.chartSeries ?? {};
    const systemOverview: SystemOverviewStats | null = props.systemOverview ?? null;

    return (
        <AdminDashboardLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Administrator · Analytics
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Analytics
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Cross-cell trends, KPI deltas, and submission time-series.
                    </p>
                </div>
            }
        >
            <Head title="Analytics · Admin" />

            <div className="space-y-8" data-testid="admin-analytics-root">
                {/* ─────────── KPI delta row ─────────── */}
                <DashboardSection
                    sectionTestId="analytics-kpi-row"
                    eyebrow="At a Glance"
                    title="Key indicators"
                    description="Headline metrics across the platform. Each delta compares the last 7 days against the prior 7."
                    icon={zapIconPath}
                >
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <KPICard accent="indigo"  caption="Total Guests"      value={kpis.totalGuests ?? 0}      delta={kpiDeltas.totalGuests}      series={kpiSeries.totalGuests}      animateValue={true} trend="all records" />
                        <KPICard accent="amber"   caption="Pending Contacts"  value={kpis.pendingContacts ?? 0}  delta={kpiDeltas.pendingContacts}  series={kpiSeries.pendingContacts}  animateValue={true} trend="not yet contacted" />
                        <KPICard accent="emerald" caption="Total Calls"       value={kpis.totalCalls ?? 0}       delta={kpiDeltas.totalCalls}       series={kpiSeries.totalCalls}       animateValue={true} trend="guests contacted" />
                        <KPICard accent="emerald" caption="Visited"           value={kpis.visited ?? 0}          delta={kpiDeltas.visited}          series={kpiSeries.visited}          animateValue={true} trend="confirmed visits" />
                        <KPICard accent="blue"    caption="Impact Cells"      value={kpis.totalCells ?? 0}       delta={kpiDeltas.totalCells}       series={kpiSeries.totalCells}       animateValue={true} trend="registered cells" />
                        <KPICard accent="amber"   caption="Submissions"       value={kpis.totalSubmissions ?? 0} delta={kpiDeltas.totalSubmissions} series={kpiSeries.totalSubmissions} animateValue={true} trend="across all forms" />
                        <KPICard accent="default" caption="Users"             value={kpis.totalUsers ?? 0}       delta={kpiDeltas.totalUsers}       series={kpiSeries.totalUsers}       animateValue={true} trend="system accounts" />
                    </div>
                </DashboardSection>

                {/* ─────────── Overview Analytics (recharts, lazy) ─────────── */}
                <DashboardSection
                    sectionTestId="analytics-overview-section"
                    eyebrow="Trends"
                    title="Overview Analytics"
                    description="Cumulative growth across the chosen range. Chart is lazy-loaded (recharts ~150 kB)."
                    icon={chartIconPath}
                    action={
                        <DateRangeFilter
                            rangeKey={rangeKey}
                            customFrom={rangeFrom}
                            customTo={rangeTo}
                            target="/admin/analytics"
                        />
                    }
                >
                    <Suspense
                        fallback={
                            <div
                                className="flex items-center justify-center rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800"
                                style={{ height: 320 }}
                                data-testid="analytics-chart-loading"
                            >
                                <span className="text-sm text-gray-500 dark:text-gray-400">Loading chart…</span>
                            </div>
                        }
                    >
                        <OverviewAnalytics series={chartSeries} labels={rangeLabels} />
                    </Suspense>
                </DashboardSection>

                {/* ─────────── System Overview ─────────── */}
                {systemOverview && <SystemOverviewPanel stats={systemOverview} />}
            </div>
        </AdminDashboardLayout>
    );
}
