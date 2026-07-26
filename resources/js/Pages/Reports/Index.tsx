import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import KPICard from '@/Components/KPICard';
import { Head, Link, router } from '@inertiajs/react';
import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, AreaChart, Area, CartesianGrid } from 'recharts';

interface StatusRow { contacted_status: string | null; cnt: number; }
interface EventRow { event: string | null; cnt: number; }
interface FollowUpRow { status: string; cnt: number; }
interface MonthlyRow { ym: string; cnt: number; }

export default function ReportsIndex({ kpis, byStatus, byEvent, byFollowUp, monthly, month }: {
    kpis: { pendingContacts: number; totalCalls: number; visited: number; pendingVisit: number; responseRate: number };
    byStatus: StatusRow[]; byEvent: EventRow[]; byFollowUp: FollowUpRow[]; monthly: MonthlyRow[]; month: string;
}) {
    const statusChartData = byStatus.map(r => ({ name: r.contacted_status ?? '(empty)', value: r.cnt }));
    const eventChartData = byEvent.map(r => ({ name: r.event ?? '(empty)', value: r.cnt }));
    const followUpChartData = byFollowUp.map(r => ({ name: r.status, value: r.cnt }));
    const monthlyChartData = monthly.map(r => ({ name: r.ym, guests: r.cnt }));

    const COLORS = ['#dc2626', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#8b5cf6'];

    return (
        <AuthenticatedLayout header={
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Reports</h2>
                <div className="flex items-center gap-2">
                    <input type="month" value={month}
                        onChange={e => router.get('/reports', { month: e.target.value }, { preserveState: true })}
                        className="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                    <Link href={route('csv.export')} className="text-sm text-red-600 hover:underline">Export CSV</Link>
                </div>
            </div>
        }>
            <Head title="Reports" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="space-y-6">
                        <section className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <KPICard caption="Pending Contacts" value={kpis.pendingContacts} trend="not yet contacted" />
                            <KPICard caption="Total Calls" value={kpis.totalCalls} trend="contacted" />
                            <KPICard caption="Visited" value={kpis.visited} trend="confirmed visits" />
                            <KPICard caption="Pending Visit" value={kpis.pendingVisit} trend="available" />
                            <KPICard caption="Response Rate" value={`${kpis.responseRate}%`} trend="visited ÷ calls" />
                        </section>

                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            {statusChartData.length > 0 && (
                                <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                    <h3 className="mb-3 text-sm font-semibold text-gray-600 dark:text-gray-400">By Contact Status</h3>
                                    <ResponsiveContainer width="100%" height={250}>
                                        <BarChart data={statusChartData}>
                                            <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                            <YAxis allowDecimals={false} />
                                            <Tooltip />
                                            <Bar dataKey="value" fill="#dc2626" radius={[4, 4, 0, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            )}

                            {followUpChartData.length > 0 && (
                                <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                    <h3 className="mb-3 text-sm font-semibold text-gray-600 dark:text-gray-400">By Follow Up Status</h3>
                                    <ResponsiveContainer width="100%" height={250}>
                                        <BarChart data={followUpChartData}>
                                            <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                            <YAxis allowDecimals={false} />
                                            <Tooltip />
                                            <Bar dataKey="value" fill="#f97316" radius={[4, 4, 0, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            )}

                            {eventChartData.length > 0 && (
                                <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                    <h3 className="mb-3 text-sm font-semibold text-gray-600 dark:text-gray-400">By Event</h3>
                                    <ResponsiveContainer width="100%" height={250}>
                                        <BarChart data={eventChartData}>
                                            <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                            <YAxis allowDecimals={false} />
                                            <Tooltip />
                                            <Bar dataKey="value" fill="#8b5cf6" radius={[4, 4, 0, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            )}

                            {monthlyChartData.length > 0 && (
                                <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                    <h3 className="mb-3 text-sm font-semibold text-gray-600 dark:text-gray-400">Monthly Trend</h3>
                                    <ResponsiveContainer width="100%" height={250}>
                                        <AreaChart data={monthlyChartData}>
                                            <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                            <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                                            <YAxis allowDecimals={false} />
                                            <Tooltip />
                                            <Area type="monotone" dataKey="guests" stroke="#dc2626" fill="#fecaca" strokeWidth={2} />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
