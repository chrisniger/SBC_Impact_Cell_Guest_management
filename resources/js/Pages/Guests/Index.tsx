import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusPill from '@/Components/StatusPill';
import { Head, Link } from '@inertiajs/react';

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
    activeRole: string | null;
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

export default function Index({ guests, canCreate, activeRole, groups }: GuestsPageProps) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        My Guests
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {groups.groupOf === 'followUpTeam' ? 'Assigned Guests' : 'Guests'}
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Active role: <span className="font-mono">{activeRole ?? '—'}</span> · Group: <span className="font-mono">{groups.groupOf ?? '—'}</span> · You can edit: <span className="font-mono">{groups.ownedByGroup.length}</span> field{groups.ownedByGroup.length === 1 ? '' : 's'}
                    </p>
                </div>
            }
        >
            <Head title="Guests" />

            <div className="space-y-4">
                {guests.data.length === 0 ? (
                    <EmptyState
                        title="No guests yet"
                        description="Once guests are added or assigned to you, they'll appear here. Use the Search & filters above to narrow down."
                        iconPath={inboxIconPath}
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-testid="guests-table">
                                <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Event</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Contacted</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Impact</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Follow Up</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Created</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {guests.data.map((g) => (
                                        <tr key={g.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
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
                )}

                {canCreate && (
                    <div className="flex justify-end">
                        <Link
                            href={route('guests.index')}
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
        </AuthenticatedLayout>
    );
}
