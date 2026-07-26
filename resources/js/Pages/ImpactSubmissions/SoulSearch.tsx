import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Hit { id: string; name: string | null; phone: string | null; gender: string | null; cell: string | null; created_at: string | null; }

export default function SoulSearch() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Hit[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (query.length < 2) { setResults([]); return; }
        const timer = setTimeout(() => {
            setLoading(true);
            fetch(`/impact-submissions/search/json?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(d => { setResults(d); setLoading(false); })
                .catch(() => setLoading(false));
        }, 300);
        return () => clearTimeout(timer);
    }, [query]);

    return (
        <AuthenticatedLayout header={
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">Soul Search</h2>
        }>
            <Head title="Soul Search" />
            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            <input type="text" value={query} onChange={e => setQuery(e.target.value)}
                                placeholder="Search by name, phone, email…"
                                className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                            {loading && <p className="mt-2 text-sm text-gray-500">Searching…</p>}
                            {!loading && query.length >= 2 && results.length === 0 && (
                                <p className="mt-4 text-sm text-gray-500">No souls found.</p>
                            )}
                            {results.length > 0 && (
                                <table className="mt-4 min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Name</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Phone</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Gender</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Cell</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                        {results.map(h => (
                                            <tr key={h.id} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td className="px-3 py-2 text-sm font-medium">{h.name ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm">{h.phone ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm">{h.gender ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm">{h.cell ?? '—'}</td>
                                                <td className="px-3 py-2 text-sm text-gray-500">{h.created_at?.slice(0, 10) ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
