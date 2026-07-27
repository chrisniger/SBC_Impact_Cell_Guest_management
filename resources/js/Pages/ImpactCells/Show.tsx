import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusPill from '@/Components/StatusPill';
import { Head, Link } from '@inertiajs/react';

interface ImpactCellDetail {
    id: string;
    name: string;
    phone: string | null;
    address: string | null;
    is_primary: boolean;
    parent_cell_id: string | null;
    parent?: { id: string; name: string } | null;
    sub_cells?: { id: string; name: string; is_primary: boolean }[];
    submission_count?: number;
    member_count?: number;
}

export default function ImpactCellsShow({ cell, activeRole }: { cell: ImpactCellDetail; activeRole: string | null; }) {
    const subCells = cell.sub_cells ?? [];
    const cellsIconPath = <><path d="M3 3h18v18H3z" /><path d="M3 9h18M9 21V9" /></>;
    const layersIconPath = <><polygon points="12 2 2 7 12 12 22 7 12 2" /><polyline points="2 17 12 22 22 17" /><polyline points="2 12 12 17 22 12" /></>;
    const arrowRightIcon = <><path d="M5 12h14" /><path d="m12 5 7 7-7 7" /></>;
    const emptyIconPath = <><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></>;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <nav className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <Link href={route('impact-cells.index')} className="transition-colors hover:text-indigo-600 dark:hover:text-indigo-400">
                            Impact Cells
                        </Link>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                            {arrowRightIcon}
                        </svg>
                        <span className="text-gray-700 dark:text-gray-300">{cell.name}</span>
                    </nav>
                    <div className="mt-2 flex items-center gap-3">
                        <h2 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{cell.name}</h2>
                        {cell.is_primary && <StatusPill tone="brand" dot>Primary</StatusPill>}
                        {!cell.is_primary && <StatusPill tone="info" dot>Sub-cell</StatusPill>}
                    </div>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Active role: <span className="font-mono">{activeRole ?? '—'}</span>
                    </p>
                </div>
            }
        >
            <Head title={cell.name} />

            <div className="space-y-6">
                {/* Hero band */}
                <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-blue-50 p-6 shadow-card dark:border-indigo-900/40 dark:from-indigo-950/40 dark:via-gray-900 dark:to-blue-950/40">
                    <div className="flex items-center gap-4">
                        <span className="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6" aria-hidden="true">
                                {cellsIconPath}
                            </svg>
                        </span>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900 dark:text-white">{cell.name}</h1>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {cell.is_primary ? 'Primary cell — anchor of outreach' : 'Sub-cell reporting to a primary'}
                            </p>
                        </div>
                    </div>
                </section>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800">
                        <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                </svg>
                            </span>
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Details</h3>
                        </header>
                        <dl className="px-5 py-4 text-sm">
                            <div className="flex items-baseline justify-between border-b border-gray-100 py-2 dark:border-gray-700">
                                <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Phone</dt>
                                <dd className="font-mono text-gray-900 dark:text-gray-100">{cell.phone ?? '—'}</dd>
                            </div>
                            <div className="flex items-baseline justify-between border-b border-gray-100 py-2 dark:border-gray-700">
                                <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Address</dt>
                                <dd className="text-gray-900 dark:text-gray-100">{cell.address ?? '—'}</dd>
                            </div>
                            <div className="flex items-baseline justify-between py-2">
                                <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Parent</dt>
                                <dd>
                                    {cell.parent ? (
                                        <Link href={route('impact-cells.show', cell.parent.id)} className="text-indigo-600 hover:underline dark:text-indigo-400">
                                            {cell.parent.name}
                                        </Link>
                                    ) : <span className="text-gray-400 dark:text-gray-500">—</span>}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="sub-cells-card">
                        <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    {layersIconPath}
                                </svg>
                            </span>
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Sub-cells</h3>
                            <span className="ml-auto text-xs text-gray-500 dark:text-gray-400">{subCells.length}</span>
                        </header>
                        <div className="px-5 py-4">
                            {subCells.length === 0 ? (
                                <EmptyState
                                    title="No sub-cells"
                                    description="This cell has no sub-cells assigned to it."
                                    iconPath={emptyIconPath}
                                />
                            ) : (
                                <ul className="space-y-2">
                                    {subCells.map((s) => (
                                        <li key={s.id}>
                                            <Link
                                                href={route('impact-cells.show', s.id)}
                                                className="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-2.5 text-sm transition-all hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20"
                                                data-testid={`sub-cell-${s.id}`}
                                            >
                                                <span className="font-medium text-gray-900 dark:text-gray-100">{s.name}</span>
                                                {s.is_primary && <StatusPill tone="brand" dot>Primary</StatusPill>}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
