import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import StatusPill from '@/Components/StatusPill';
import { Head, Link } from '@inertiajs/react';

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

const TYPE_LABEL: Record<string, string> = {
    member: 'Members Data',
    report: 'Cell Report',
    childbirth: 'Childbirth',
    soul: 'Soul Registration',
};

const TYPE_TONE: Record<string, 'info' | 'success' | 'warn' | 'brand' | 'neutral'> = {
    member: 'info',
    report: 'success',
    childbirth: 'warn',
    soul: 'brand',
};

const fileIconPath = <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /></>;

export default function Show({ submission }: { submission: Submission }) {
    const title = TYPE_LABEL[submission.type] ?? submission.type;
    const subjectName = submission.data?.full_name ?? submission.data?.child_name ?? submission.data?.name ?? submission.id.slice(0, 8);

    return (
        <AdminDashboardLayout header={
            <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                    Submission
                </p>
                <div className="mt-1 flex items-center gap-3">
                    <h2 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{title}</h2>
                    <StatusPill tone={TYPE_TONE[submission.type] ?? 'neutral'} dot>{title}</StatusPill>
                </div>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {subjectName} ·{' '}
                    {submission.created_at?.slice(0, 10) ?? '—'}
                </p>
            </div>
        }>
            <Head title={title} />

            <div className="space-y-6">
                <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-blue-50 p-6 shadow-card dark:border-indigo-900/40 dark:from-indigo-950/40 dark:via-gray-900 dark:to-blue-950/40">
                    <div className="flex items-center gap-4">
                        <span className="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6" aria-hidden="true">
                                {fileIconPath}
                            </svg>
                        </span>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900 dark:text-white">{subjectName}</h1>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {title} · ID <span className="font-mono">{submission.id.slice(0, 8)}</span>
                            </p>
                        </div>
                    </div>
                </section>

                <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="card-show-meta">
                    <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Meta</h3>
                    </header>
                    <dl className="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Type</dt>
                            <dd className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">{title}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Impact Cell</dt>
                            <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {submission.impact_cell ? (
                                    <Link href={route('impact-cells.show', submission.impact_cell.id)} className="text-indigo-600 hover:underline dark:text-indigo-400">
                                        {submission.impact_cell.name}
                                    </Link>
                                ) : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Submitted By</dt>
                            <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{submission.user?.name ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</dt>
                            <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{submission.created_at?.slice(0, 10) ?? '—'}</dd>
                        </div>
                        {submission.fellowship_date_key && (
                            <div>
                                <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Fellowship Date</dt>
                                <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{submission.fellowship_date_key}</dd>
                            </div>
                        )}
                        <div>
                            <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Updated</dt>
                            <dd className="mt-1 text-sm text-gray-900 dark:text-gray-100">{submission.updated_at?.slice(0, 10) ?? '—'}</dd>
                        </div>
                    </dl>
                </section>

                <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="card-show-data">
                    <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Submitted Data</h3>
                    </header>
                    <div className="grid grid-cols-1 gap-4 px-5 py-5 sm:grid-cols-2">
                        {submission.data && Object.entries(submission.data).length > 0 ? (
                            Object.entries(submission.data).map(([key, val]) => (
                                <div key={key}>
                                    <dt className="text-xs font-medium uppercase tracking-wider text-gray-400">{key.replace(/_/g, ' ')}</dt>
                                    <dd className="mt-1 break-words text-sm text-gray-900 dark:text-gray-100">
                                        {val === null || val === '' ? '—' : String(val)}
                                    </dd>
                                </div>
                            ))
                        ) : (
                            <p className="col-span-2 text-sm text-gray-500">No data submitted.</p>
                        )}
                    </div>
                </section>
            </div>
        </AdminDashboardLayout>
    );
}
