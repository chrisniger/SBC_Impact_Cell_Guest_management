import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

interface ReportRow {
    id: string;
    type: string;
    data: Record<string, any>;
    fellowship_date_key: string | null;
    impact_cell: { id: string; name: string } | null;
    created_at: string | null;
}

export default function MyReports({ reports, activeRole }: { reports: { data: ReportRow[] }; activeRole: string | null }) {
    return (
        <AuthenticatedLayout header={
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">My Reports</h2>
        }>
            <Head title="My Reports" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            {reports.data.length === 0 ? (
                                <p className="text-sm text-gray-500">No reports submitted yet.</p>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Cell</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Preview</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                        {reports.data.map((r) => (
                                            <tr key={r.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td className="px-3 py-2 text-sm capitalize">{r.type}</td>
                                                <td className="px-3 py-2 text-sm">{r.impact_cell?.name ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm text-gray-500 truncate max-w-xs">
                                                    {r.type === 'member' ? `${r.data.full_name ?? r.data.name ?? ''}` :
                                                     r.type === 'report' ? `Attendance: ${r.data.adults ?? 0} adults, ${r.data.children ?? 0} children` :
                                                     r.type === 'childbirth' ? `${r.data.child_name ?? ''}` :
                                                     r.type === 'soul' ? `${r.data.full_name ?? r.data.name ?? ''}` : '—'}
                                                </td>
                                                <td className="px-3 py-2 text-sm">{r.created_at?.slice(0, 10) ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
