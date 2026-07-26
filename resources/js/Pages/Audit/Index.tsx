import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

interface Entry {
    id: number; description: string; actor: string;
    subjectType: string; subjectId: string | null;
    properties: Record<string, any> | null;
    createdAt: string | null;
}

export default function AuditIndex({ entries }: { entries: Entry[] }) {
    const [selected, setSelected] = useState<Entry | null>(null);

    return (
        <AuthenticatedLayout header={
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Audit Log</h2>
        }>
            <Head title="Audit Log" />
            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            {entries.length === 0 ? (
                                <p className="text-sm text-gray-500">No audit entries yet.</p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr>
                                                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Actor</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Description</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Subject</th>
                                                <th className="px-3 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                            {entries.map(e => (
                                                <tr key={e.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td className="px-3 py-2 text-sm text-gray-500">{e.createdAt?.slice(0, 10) ?? '—'}</td>
                                                    <td className="px-3 py-2 text-sm">{e.actor}</td>
                                                    <td className="px-3 py-2 text-sm">{e.description}</td>
                                                    <td className="px-3 py-2 text-sm text-gray-500">{e.subjectType}#{e.subjectId?.slice(0, 8) ?? ''}</td>
                                                    <td className="px-3 py-2 text-right">
                                                        <button onClick={() => setSelected(selected?.id === e.id ? null : e)}
                                                            className="text-xs text-red-600 hover:underline">
                                                            {selected?.id === e.id ? 'Close' : 'Details'}
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {selected && (
                                <div className="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                    <h3 className="mb-2 text-sm font-semibold text-gray-700">Entry #{selected.id}</h3>
                                    <pre className="overflow-x-auto text-xs text-gray-600">{JSON.stringify(selected.properties, null, 2)}</pre>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
