import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import EmptyState from '@/Components/EmptyState';
import ReadOnlyBanner from '@/Components/ReadOnlyBanner';
import StatusPill from '@/Components/StatusPill';
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

const TYPE_LABEL: Record<string, string> = {
    member: 'Members Data',
    report: 'Cell Report',
    childbirth: 'Childbirth',
    soul: 'Soul',
};

const TYPE_TONE: Record<string, 'info' | 'success' | 'warn' | 'brand' | 'neutral'> = {
    member: 'info',
    report: 'success',
    childbirth: 'warn',
    soul: 'brand',
};

const fileIconPath = <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="9" y1="13" x2="15" y2="13" /><line x1="9" y1="17" x2="15" y2="17" /></>;

export default function Index({ submissions, activeRole, canCreate }: { submissions: { data: SubmissionRow[] }; activeRole: string | null; canCreate: boolean }) {
    return (
        <AdminDashboardLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                            Outreach
                        </p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Impact Submissions
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Active role: <span className="font-mono">{activeRole ?? '—'}</span> · {submissions.data.length} recent
                        </p>
                    </div>
                    {canCreate && (
                        <Link
                            href={route('impact-submissions.create')}
                            className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            data-testid="new-submission-link"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            New Submission
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Impact Submissions" />

            {/* Phase 36 — zonal coordinators view cell activity read-only. */}
            {activeRole === 'Impact_Zonal_Coordinator' && (
                <ReadOnlyBanner
                    testId="impact-submissions-zonal-readonly-banner"
                    description="Zonal Coordinators can view the activity of their assigned cells but cannot submit reports. Only Impact Cell leaders submit."
                />
            )}

            {submissions.data.length === 0 ? (
                <EmptyState
                    title="No submissions yet"
                    description="Once member, report, childbirth, or soul records are logged, they'll appear here."
                    iconPath={fileIconPath}
                />
            ) : (
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-testid="submissions-table">
                            <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Cell</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Submitted By</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                                    <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Details</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {submissions.data.map((s) => (
                                    <tr key={s.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                        <td className="px-4 py-3 text-sm">
                                            <Link href={route('impact-submissions.show', s.id)} data-testid={`submission-detail-${s.id}`}>
                                                <StatusPill tone={TYPE_TONE[s.type] ?? 'neutral'} dot>
                                                    {TYPE_LABEL[s.type] ?? s.type}
                                                </StatusPill>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-sm">
                                            <Link
                                                href={route('impact-submissions.show', s.id)}
                                                className="font-medium text-gray-900 transition-colors hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                                            >
                                                {s.impact_cell?.name ?? '—'}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{s.user?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{s.created_at?.slice(0, 10) ?? '—'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={route('impact-submissions.show', s.id)}
                                                className="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-500/50 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300"
                                                data-testid={`submission-view-${s.id}`}
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                                View details
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </AdminDashboardLayout>
    );
}
