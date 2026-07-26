import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

interface IndexProps {
    hierarchy: boolean;
    totalCount: number;
    primaryCount: number;
    subCellCount: number;
}

export default function Index({
    hierarchy,
    totalCount,
    primaryCount,
    subCellCount,
}: IndexProps) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Impact Cells
                </h2>
            }
        >
            <Head title="Impact Cells" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                Phase 03 stub — the Impact Cells data layer is in place
                                (Phase 03 routes + controller + 69 cells seeded).
                                Phase 04 will flesh this out into the full admin UI
                                (tree view with drag-to-re-attach sub-cells).
                            </p>
                            <dl className="grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <dt className="font-medium text-gray-500 dark:text-gray-400">Total</dt>
                                    <dd className="mt-1 text-2xl font-semibold">{totalCount}</dd>
                                </div>
                                <div>
                                    <dt className="font-medium text-gray-500 dark:text-gray-400">Primary</dt>
                                    <dd className="mt-1 text-2xl font-semibold">{primaryCount}</dd>
                                </div>
                                <div>
                                    <dt className="font-medium text-gray-500 dark:text-gray-400">Sub-cells</dt>
                                    <dd className="mt-1 text-2xl font-semibold">{subCellCount}</dd>
                                </div>
                            </dl>
                            {hierarchy && (
                                <p className="mt-4 text-xs text-amber-600 dark:text-amber-400">
                                    ?hierarchy=1: hierarchical tree view — coming in Phase 04.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}