import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

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

export default function Index({ guests, canCreate, activeRole, groups }: GuestsPageProps) {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Guests
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Active role: <span className="font-mono">{activeRole ?? '—'}</span>
                        {' · '}
                        Group: <span className="font-mono">{groups.groupOf ?? '—'}</span>
                        {' · '}
                        You can edit: <span className="font-mono">{groups.ownedByGroup.length}</span> fields
                    </p>
                </div>
            }
        >
            <Head title="Guests" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            {/* Phase 04 ships the data layer — full UI lands in Phase 05/06/07.
                                For now we render a simple table so the route is exercised. */}
                            {guests.data.length === 0 ? (
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    No guests yet.
                                </p>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Event</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Contacted</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Impact</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Follow Up</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                        {guests.data.map((g) => (
                                            <tr key={g.id}>
                                                <td className="px-3 py-2 text-sm">{g.guest_name}</td>
                                                <td className="px-3 py-2 text-sm">{g.event ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm">{g.contacted_status ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm">{g.impact_status ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm">{g.follow_up_status ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm">{g.created_at?.slice(0, 10) ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}

                            {canCreate && (
                                <p className="mt-6 text-sm text-gray-600 dark:text-gray-400">
                                    ➕ Create form ships with Phase 05.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
