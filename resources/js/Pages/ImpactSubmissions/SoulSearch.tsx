import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusPill from '@/Components/StatusPill';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Hit {
    id: string;
    name: string | null;
    phone: string | null;
    gender: string | null;
    cell: string | null;
    created_at: string | null;
    type?: string;
}

const searchIconPath = <><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></>;
const emptyIconPath = <><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></>;

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
            <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                    Search
                </p>
                <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Soul Search
                </h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Find any soul or member by name, phone, or email.
                </p>
            </div>
        }>
            <Head title="Soul Search" />

            <div className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800">
                <div className="p-6">
                    <div className="relative">
                        <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                {searchIconPath}
                            </svg>
                        </span>
                        <input
                            type="text"
                            value={query}
                            onChange={e => setQuery(e.target.value)}
                            placeholder="Search by name, phone, email…"
                            data-testid="soul-search-input"
                            className="block w-full rounded-lg border-gray-300 pl-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:text-sm"
                        />
                    </div>

                    {loading && (
                        <p className="mt-3 inline-flex items-center gap-2 text-sm text-gray-500">
                            <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 animate-spin" aria-hidden="true">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            Searching…
                        </p>
                    )}

                    {!loading && query.length >= 2 && results.length === 0 && (
                        <div className="mt-6">
                            <EmptyState
                                title="No souls found"
                                description="Try a different name, phone, or email."
                                iconPath={emptyIconPath}
                            />
                        </div>
                    )}

                    {results.length > 0 && (
                        <div className="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-testid="soul-search-results">
                                <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Phone</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Gender</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Cell</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {results.map(h => (
                                        <tr key={h.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                            <td className="px-4 py-3 text-sm">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-gray-900 dark:text-gray-100">{h.name ?? '—'}</span>
                                                    {h.type && <StatusPill tone={h.type === 'soul' ? 'brand' : 'info'} size="sm">{h.type}</StatusPill>}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{h.phone ?? '—'}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{h.gender ?? '—'}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{h.cell ?? '—'}</td>
                                            <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{h.created_at?.slice(0, 10) ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
