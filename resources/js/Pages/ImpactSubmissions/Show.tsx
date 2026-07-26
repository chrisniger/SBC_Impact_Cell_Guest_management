import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

interface Submission {
    id: string;
    type: string;
    data: Record<string, any>;
    fellowship_date_key: string | null;
    impact_cell: { id: string; name: string } | null;
    user: { id: number; name: string } | null;
    created_at: string | null;
    updated_at: string | null;
}

export default function Show({ submission }: { submission: Submission }) {
    const labels: Record<string, string> = { member: 'Members Data', report: 'Cell Report', childbirth: 'Childbirth', soul: 'Soul Registration' };
    const title = labels[submission.type] ?? submission.type;

    return (
        <AuthenticatedLayout header={
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {title} — {submission.data?.full_name ?? submission.data?.child_name ?? submission.data?.name ?? submission.id.slice(0, 8)}
            </h2>
        }>
            <Head title={title} />
            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs font-medium uppercase tracking-wider text-gray-500">Type</dt>
                                    <dd className="mt-1 text-sm capitalize text-gray-900 dark:text-gray-100">{title}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-medium uppercase tracking-wider text-gray-500">Impact Cell</dt>
                                    <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{submission.impact_cell?.name ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-medium uppercase tracking-wider text-gray-500">Submitted By</dt>
                                    <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{submission.user?.name ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-medium uppercase tracking-wider text-gray-500">Date</dt>
                                    <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{submission.created_at?.slice(0, 10) ?? '—'}</dd>
                                </div>
                                {submission.fellowship_date_key && (
                                    <div>
                                        <dt className="text-xs font-medium uppercase tracking-wider text-gray-500">Fellowship Date</dt>
                                        <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{submission.fellowship_date_key}</dd>
                                    </div>
                                )}
                            </dl>
                            <hr className="my-6 border-gray-200 dark:border-gray-700" />
                            <h3 className="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500">Submitted Data</h3>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {submission.data && Object.entries(submission.data).map(([key, val]) => (
                                    <div key={key}>
                                        <dt className="text-xs font-medium uppercase tracking-wider text-gray-400">
                                            {key.replace(/_/g, ' ')}
                                        </dt>
                                        <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                            {val === null || val === '' ? '—' : String(val)}
                                        </dd>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
