import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusPill from '@/Components/StatusPill';
import { Head, Link } from '@inertiajs/react';

interface ReportRow {
    id: string;
    type: string;
    data: Record<string, any>;
    fellowship_date_key: string | null;
    impact_cell: { id: string; name: string } | null;
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

function preview(r: ReportRow): string {
    switch (r.type) {
        case 'member':     return r.data.full_name ?? r.data.name ?? '—';
        case 'report':     return `Attendance: ${r.data.adults ?? 0} adults, ${r.data.children ?? 0} children`;
        case 'childbirth': return r.data.child_name ?? '—';
        case 'soul':       return r.data.full_name ?? r.data.name ?? '—';
        default:           return '—';
    }
}

const fileIconPath = <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="9" y1="13" x2="15" y2="13" /><line x1="9" y1="17" x2="15" y2="17" /></>;

export default function MyReports({ reports, activeRole }: { reports: { data: ReportRow[] }; activeRole: string | null }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                            Personal
                        </p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            My Reports
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Active role: <span className="font-mono">{activeRole ?? '—'}</span> · {reports.data.length} submission{reports.data.length === 1 ? '' : 's'}
                        </p>
                    </div>
                    <Link
                        href={route('impact-submissions.create')}
                        className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        New Submission
                    </Link>
                </div>
            }
        >
            <Head title="My Reports" />

            {reports.data.length === 0 ? (
                <EmptyState
                    title="No reports submitted yet"
                    description="Your member, report, childbirth, and soul records will appear here."
                    iconPath={fileIconPath}
                />
            ) : (
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-testid="my-reports-table">
                            <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Cell</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Preview</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {reports.data.map((r) => (
                                    <tr key={r.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                        <td className="px-4 py-3 text-sm">
                                            <StatusPill tone={TYPE_TONE[r.type] ?? 'neutral'} dot>
                                                {TYPE_LABEL[r.type] ?? r.type}
                                            </StatusPill>
                                        </td>
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{r.impact_cell?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate">{preview(r)}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{r.created_at?.slice(0, 10) ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
