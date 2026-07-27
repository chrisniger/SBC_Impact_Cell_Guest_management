import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusPill from '@/Components/StatusPill';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

interface Entry {
    id: number;
    description: string;
    actor: string;
    subjectType: string;
    subjectId: string | null;
    properties: Record<string, any> | null;
    createdAt: string | null;
}

const shieldIconPath = <><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></>;

export default function AuditIndex({ entries }: { entries: Entry[] }) {
    const [selected, setSelected] = useState<Entry | null>(null);

    return (
        <AuthenticatedLayout header={
            <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                    Compliance
                </p>
                <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Audit Log
                </h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {entries.length} entr{entries.length === 1 ? 'y' : 'ies'} on file · most recent activity first
                </p>
            </div>
        }>
            <Head title="Audit Log" />

            <div className="space-y-6">
                <section className="motion-safe:animate-[fadeIn_0.4s_ease-out] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800" data-testid="card-audit">
                    <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                {shieldIconPath}
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Entries</h3>
                    </header>
                    <div className="overflow-x-auto">
                        {entries.length === 0 ? (
                            <EmptyState
                                title="No audit entries yet"
                                description="Once activity happens in the system, it'll be logged here."
                                iconPath={shieldIconPath}
                                className="rounded-none border-0 shadow-none"
                            />
                        ) : (
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-testid="audit-table">
                                <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Actor</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Description</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Subject</th>
                                        <th className="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {entries.map(e => (
                                        <tr key={e.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                            <td className="px-4 py-3 text-sm font-mono text-gray-500 dark:text-gray-400">{e.createdAt?.slice(0, 10) ?? '—'}</td>
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{e.actor}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{e.description}</td>
                                            <td className="px-4 py-3 text-sm">
                                                <StatusPill tone="neutral" size="sm">
                                                    {e.subjectType}#{e.subjectId?.slice(0, 8) ?? ''}
                                                </StatusPill>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => setSelected(selected?.id === e.id ? null : e)}
                                                    className="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 transition-colors hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                >
                                                    {selected?.id === e.id ? 'Close' : 'Details'}
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </section>

                {selected && (
                    <section className="motion-safe:animate-[fadeIn_0.4s_ease-out] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800" data-testid="card-audit-details">
                        <header className="flex items-center justify-between border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                                Entry #{selected.id} · {selected.description}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setSelected(null)}
                                className="text-xs font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                            >
                                Close
                            </button>
                        </header>
                        <pre className="overflow-x-auto p-5 text-xs leading-relaxed text-gray-700 dark:text-gray-300">
                            {JSON.stringify(selected.properties, null, 2)}
                        </pre>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
