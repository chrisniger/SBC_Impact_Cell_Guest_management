import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusPill from '@/Components/StatusPill';
import { Head, Link } from '@inertiajs/react';

interface ImpactCellRow {
    id: string;
    name: string;
    phone: string | null;
    address: string | null;
    is_primary: boolean;
    parent_cell_id: string | null;
    parent?: { id: string; name: string } | null;
    order?: number | null;
}

export default function ImpactCellsIndex({ cells, activeRole }: { cells: ImpactCellRow[]; activeRole: string | null; }) {
    const primaryCount = cells.filter(c => c.is_primary).length;
    const subCellCount = cells.filter(c => !c.is_primary).length;

    const cellsIconPath = <><path d="M3 3h18v18H3z" /><path d="M3 9h18M9 21V9" /></>;
    const emptyIconPath = <><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></>;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Outreach Network
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Impact Cells
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {primaryCount} primary · {subCellCount} sub-cell{subCellCount === 1 ? '' : 's'} ·{' '}
                        Active role: <span className="font-mono">{activeRole ?? '—'}</span>
                    </p>
                </div>
            }
        >
            <Head title="Impact Cells" />

            {cells.length === 0 ? (
                <EmptyState
                    title="No impact cells yet"
                    description="Once cells are seeded or admin adds them, they'll appear here."
                    iconPath={emptyIconPath}
                />
            ) : (
                <div className="space-y-6">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="impact-cells-grid">
                        {cells.map((c) => (
                            <Link
                                key={c.id}
                                href={route('impact-cells.show', c.id)}
                                className="group relative flex flex-col gap-3 overflow-hidden rounded-xl border border-gray-200 bg-white p-5 shadow-[0_4px_20px_rgba(0,0,0,0.03)] transition-all hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)] dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-500"
                                data-testid={`impact-cell-${c.id}`}
                            >
                                <span aria-hidden className="pointer-events-none absolute -right-8 -top-8 h-24 w-24 rounded-full bg-indigo-50/60 opacity-0 transition-opacity group-hover:opacity-100 dark:bg-indigo-900/20" />
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                                {cellsIconPath}
                                            </svg>
                                        </span>
                                        <h3 className="text-base font-semibold text-gray-900 dark:text-white">{c.name}</h3>
                                    </div>
                                    {c.is_primary && <StatusPill tone="brand" dot>Primary</StatusPill>}
                                </div>
                                {c.parent && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Parent: <span className="font-medium">{c.parent.name}</span>
                                    </p>
                                )}
                                {c.phone && (
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        <span className="font-mono">{c.phone}</span>
                                    </p>
                                )}
                                <div className="mt-auto flex items-center justify-between">
                                    <span className="text-xs text-gray-400 dark:text-gray-500">
                                        Order: <span className="font-mono">{c.order ?? '—'}</span>
                                    </span>
                                    <span className="inline-flex items-center text-xs font-medium text-indigo-600 transition-transform group-hover:translate-x-0.5 dark:text-indigo-400">
                                        View →
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
