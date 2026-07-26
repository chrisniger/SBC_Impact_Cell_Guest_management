import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

interface SubCell {
    id: string;
    name: string;
}

interface Cell {
    id: string;
    name: string;
    phone: string | null;
    address: string | null;
    parent_cell_id: string | null;
    is_primary: boolean;
    order: number;
    sub_cells: SubCell[];
}

export default function Show({ cell }: { cell: Cell }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {cell.name}
                </h2>
            }
        >
            <Head title={cell.name} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                        <div className="p-6 text-gray-900 dark:text-gray-100">
                            <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                Phase 03 stub — full Impact Cell admin UI lands in Phase 04.
                            </p>
                            <dl className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt className="font-medium text-gray-500 dark:text-gray-400">Type</dt>
                                    <dd>{cell.is_primary ? 'Primary' : 'Sub-cell'}</dd>
                                </div>
                                <div>
                                    <dt className="font-medium text-gray-500 dark:text-gray-400">Order</dt>
                                    <dd>{cell.order}</dd>
                                </div>
                                <div>
                                    <dt className="font-medium text-gray-500 dark:text-gray-400">Phone</dt>
                                    <dd>{cell.phone ?? '—'}</dd>
                                </div>
                                <div>
                                    <dt className="font-medium text-gray-500 dark:text-gray-400">Address</dt>
                                    <dd>{cell.address ?? '—'}</dd>
                                </div>
                            </dl>
                            {cell.sub_cells && cell.sub_cells.length > 0 && (
                                <div className="mt-6">
                                    <h3 className="font-medium">Sub-cells ({cell.sub_cells.length})</h3>
                                    <ul className="mt-2 list-disc pl-5">
                                        {cell.sub_cells.map((s) => (
                                            <li key={s.id}>{s.name}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}