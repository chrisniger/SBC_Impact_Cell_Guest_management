import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import KPICard from '@/Components/KPICard';
import { Head, Link, router } from '@inertiajs/react';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, AreaChart, Area, CartesianGrid } from 'recharts';

interface StatusRow { contacted_status: string | null; cnt: number; }
interface EventRow { event: string | null; cnt: number; }
interface FollowUpRow { status: string; cnt: number; }
interface MonthlyRow { ym: string; cnt: number; }

const barIconPath = <><line x1="12" y1="20" x2="12" y2="10" /><line x1="18" y1="20" x2="18" y2="4" /><line x1="6" y1="20" x2="6" y2="16" /></>;
const phoneIconPath = <><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" /></>;
const eventIconPath = <><rect x="3" y="4" width="18" height="18" rx="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></>;
const trendIconPath = <><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" /></>;

export default function ReportsIndex({ kpis, byStatus, byEvent, byFollowUp, monthly, month }: {
    kpis: { pendingContacts: number; totalCalls: number; visited: number; pendingVisit: number; responseRate: number };
    byStatus: StatusRow[]; byEvent: EventRow[]; byFollowUp: FollowUpRow[]; monthly: MonthlyRow[]; month: string;
}) {
    const statusChartData = byStatus.map(r => ({ name: r.contacted_status ?? '(empty)', value: r.cnt }));
    const eventChartData = byEvent.map(r => ({ name: r.event ?? '(empty)', value: r.cnt }));
    const followUpChartData = byFollowUp.map(r => ({ name: r.status, value: r.cnt }));
    const monthlyChartData = monthly.map(r => ({ name: r.ym, guests: r.cnt }));

    return (
        <AuthenticatedLayout header={
            <div className="flex items-center justify-between gap-4">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Analytics
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Reports
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        High-level view of outreach, follow-up, and integration.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <input
                        type="month"
                        value={month}
                        onChange={e => router.get('/reports', { month: e.target.value }, { preserveState: true })}
                        className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    />
                    <Link
                        href={route('csv.export')}
                        className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                        </svg>
                        Export CSV
                    </Link>
                </div>
            </div>
        }>
            <Head title="Reports" />

            <div className="space-y-6">
                <section className="motion-safe:animate-[fadeIn_0.4s_ease-out]">
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                        <KPICard accent="amber"   caption="Pending Contacts" value={kpis.pendingContacts} trend="not yet contacted" />
                        <KPICard accent="emerald" caption="Total Calls"      value={kpis.totalCalls}      trend="contacted" />
                        <KPICard accent="emerald" caption="Visited"          value={kpis.visited}         trend="confirmed visits" />
                        <KPICard accent="indigo"  caption="Pending Visit"    value={kpis.pendingVisit}    trend="available" />
                        <KPICard accent="default" caption="Response Rate"    value={`${kpis.responseRate}%`} trend="visited ÷ calls" />
                    </div>
                </section>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {statusChartData.length > 0 && (
                        <ChartCard title="By Contact Status" iconPath={phoneIconPath} testId="card-status-chart">
                            <ResponsiveContainer width="100%" height={250}>
                                <BarChart data={statusChartData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                    <YAxis allowDecimals={false} />
                                    <Tooltip />
                                    <Bar dataKey="value" fill="#dc2626" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </ChartCard>
                    )}

                    {followUpChartData.length > 0 && (
                        <ChartCard title="By Follow Up Status" iconPath={barIconPath} testId="card-followup-chart">
                            <ResponsiveContainer width="100%" height={250}>
                                <BarChart data={followUpChartData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                    <YAxis allowDecimals={false} />
                                    <Tooltip />
                                    <Bar dataKey="value" fill="#f97316" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </ChartCard>
                    )}

                    {eventChartData.length > 0 && (
                        <ChartCard title="By Event" iconPath={eventIconPath} testId="card-event-chart">
                            <ResponsiveContainer width="100%" height={250}>
                                <BarChart data={eventChartData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                    <YAxis allowDecimals={false} />
                                    <Tooltip />
                                    <Bar dataKey="value" fill="#8b5cf6" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </ChartCard>
                    )}

                    {monthlyChartData.length > 0 && (
                        <ChartCard title="Monthly Trend" iconPath={trendIconPath} testId="card-monthly-chart">
                            <ResponsiveContainer width="100%" height={250}>
                                <AreaChart data={monthlyChartData}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                    <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                    <YAxis allowDecimals={false} />
                                    <Tooltip />
                                    <Area type="monotone" dataKey="guests" stroke="#dc2626" fill="#fecaca" strokeWidth={2} />
                                </AreaChart>
                            </ResponsiveContainer>
                        </ChartCard>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function ChartCard({ title, iconPath, children, testId }: { title: string; iconPath: React.ReactNode; children: React.ReactNode; testId?: string }) {
    return (
        <section
            className="motion-safe:animate-[fadeIn_0.4s_ease-out] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800"
            data-testid={testId}
        >
            <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                        {iconPath}
                    </svg>
                </span>
                <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">{title}</h3>
            </header>
            <div className="p-4">{children}</div>
        </section>
    );
}
