import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface SubmissionRow {
    id: string;
    type: string;
    data: Record<string, any>;
    fellowship_date_key: string | null;
    impact_cell: { id: string; name: string } | null;
    user: { id: number; name: string } | null;
    created_at: string | null;
}

export default function Index({ submissions, activeRole, canCreate }: { submissions: { data: SubmissionRow[] }; activeRole: string | null; canCreate: boolean }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Impact Submissions
                    </h2>
                    {canCreate && (
                        <Link href={route('impact-submissions.index')}
                            className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 dark:bg-gray-200 dark:text-gray-800">
                            New Submission
                        </Link>
                    )}
                </div>
            }>
            <Head title="Impact Submissions" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            {submissions.data.length === 0 ? (
                                <p className="text-sm text-gray-500">No submissions yet.</p>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Cell</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Submitted By</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                        {submissions.data.map((s) => (
                                            <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td className="px-3 py-2 text-sm capitalize">{s.type}</td>
                                                <td className="px-3 py-2 text-sm">{s.impact_cell?.name ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm">{s.user?.name ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm">{s.created_at?.slice(0, 10) ?? '—'}</td>
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
