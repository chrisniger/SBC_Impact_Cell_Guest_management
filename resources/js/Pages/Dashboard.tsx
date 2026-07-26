import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import KPICard from '@/Components/KPICard';
import StatusPill from '@/Components/StatusPill';
import { Head, Link } from '@inertiajs/react';

type OfficerKpis = {
    pendingContacts: number;
    totalCalls: number;
    visited: number;
    pendingVisit: number;
    responseRate: number;
};

type QueueRow = {
    id: string;
    guestName: string;
    phone: string | null;
    contactedStatus: string | null;
    visited: boolean;
    createdAt: string | null;
};

type DashboardPageProps = {
    variant: 'officer' | 'admin';
    kpis: OfficerKpis | null;
    queue: QueueRow[];
    activeRole: string | null;
    activeGroup: string | null;
};

/**
 * Dashboard — Phase 05.
 *
 * Selects layout by `props.variant`. The variant is decided server-side
 * in `DashboardController::index()` based on `User::activeGroup()` — the
 * single source of truth. New groups (Team, Cell Leader) will add new
 * variants in Phase 06 / Phase 07.
 */
export default function Dashboard({ variant, kpis, queue, activeRole, activeGroup }: DashboardPageProps) {
    return (
        <AuthenticatedLayout
            header={
                variant === 'officer'
                    ? <OfficerHeader activeRole={activeRole} />
                    : <AdminHeader activeRole={activeRole} activeGroup={activeGroup} />
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {variant === 'officer' ? (
                        <OfficerDashboard kpis={kpis!} queue={queue} />
                    ) : (
                        <AdminDashboard />
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

function AdminHeader({ activeRole, activeGroup }: { activeRole: string | null; activeGroup: string | null }) {
    return (
        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Dashboard
        </h2>
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

function AdminDashboard() {
    return (
        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
            <div className="p-6 text-gray-900 dark:text-gray-100">
                You're logged in!
            </div>
        </div>
    );
}
