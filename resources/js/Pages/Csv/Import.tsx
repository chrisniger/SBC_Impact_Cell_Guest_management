import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import StatusPill from '@/Components/StatusPill';
import { Head } from '@inertiajs/react';
import { useRef, useState } from 'react';

const uploadIconPath = (
    <>
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <polyline points="17 8 12 3 7 8" />
        <line x1="12" y1="3" x2="12" y2="15" />
    </>
);

const fileIconPath = (
    <>
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <polyline points="14 2 14 8 20 8" />
    </>
);

export default function CsvImport() {
    const [file, setFile] = useState<File | null>(null);
    const [result, setResult] = useState<{ created: number; skipped: number; errors: string[] } | null>(null);
    const [loading, setLoading] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const handleUpload = async () => {
        if (!file) return;
        setLoading(true);
        setResult(null);
        const formData = new FormData();
        formData.append('csv', file);
        try {
            const res = await fetch('/csv/import', { method: 'POST', body: formData });
            const json = await res.json();
            setResult(json);
        } catch {
            setResult({ created: 0, skipped: 0, errors: ['Upload failed.'] });
        }
        setLoading(false);
    };

    return (
        <AuthenticatedLayout header={
            <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                    Data
                </p>
                <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    CSV Import
                </h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Bulk-add guests from a CSV file. Headers: guest_name, phone, email, event, source.
                </p>
            </div>
        }>
            <Head title="CSV Import" />

            <div className="space-y-6">
                <section className="motion-safe:animate-[fadeIn_0.4s_ease-out] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800" data-testid="card-csv-import">
                    <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                {uploadIconPath}
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Upload CSV</h3>
                    </header>
                    <div className="p-6">
                        <div
                            onClick={() => inputRef.current?.click()}
                            onDragOver={(e) => { e.preventDefault(); }}
                            onDrop={(e) => { e.preventDefault(); setFile(e.dataTransfer.files?.[0] ?? null); }}
                            className="group flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/50 px-6 py-12 transition-colors hover:border-indigo-400 hover:bg-indigo-50/50 dark:border-gray-600 dark:bg-gray-900/40 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20"
                            data-testid="csv-drop-zone"
                        >
                            {file ? (
                                <div className="flex items-center gap-3">
                                    <span className="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-5 w-5" aria-hidden="true">
                                            {fileIconPath}
                                        </svg>
                                    </span>
                                    <div>
                                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">{file.name}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">{(file.size / 1024).toFixed(1)} KB</p>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <span className="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 transition-transform group-hover:scale-110 dark:bg-indigo-900/40 dark:text-indigo-300">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6" aria-hidden="true">
                                            {uploadIconPath}
                                        </svg>
                                    </span>
                                    <p className="text-sm font-semibold text-gray-700 dark:text-gray-200">Drop CSV file here or click to browse</p>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Headers: guest_name, phone, email, event, source</p>
                                </>
                            )}
                            <input
                                ref={inputRef}
                                type="file"
                                accept=".csv,.txt"
                                className="hidden"
                                onChange={e => setFile(e.target.files?.[0] ?? null)}
                                data-testid="csv-file-input"
                            />
                        </div>

                        {file && (
                            <div className="mt-4 flex justify-end">
                                <button
                                    type="button"
                                    onClick={handleUpload}
                                    disabled={loading}
                                    className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    data-testid="csv-upload-button"
                                >
                                    {loading ? (
                                        <>
                                            <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 animate-spin" aria-hidden="true">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                            </svg>
                                            Uploading…
                                        </>
                                    ) : (
                                        <>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                                {uploadIconPath}
                                            </svg>
                                            Upload & Import
                                        </>
                                    )}
                                </button>
                            </div>
                        )}
                    </div>
                </section>

                {result && (
                    <section className="motion-safe:animate-[fadeIn_0.4s_ease-out] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800" data-testid="card-csv-result">
                        <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                    <polyline points="22 4 12 14.01 9 11.01" />
                                </svg>
                            </span>
                            <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Result</h3>
                        </header>
                        <div className="p-5">
                            <div className="flex flex-wrap items-center gap-3">
                                <StatusPill tone="success" dot>
                                    Created: <span className="ml-1 font-semibold">{result.created}</span>
                                </StatusPill>
                                <StatusPill tone="warn" dot>
                                    Skipped: <span className="ml-1 font-semibold">{result.skipped}</span>
                                </StatusPill>
                                {result.errors.length > 0 && (
                                    <StatusPill tone="danger" dot>
                                        Errors: <span className="ml-1 font-semibold">{result.errors.length}</span>
                                    </StatusPill>
                                )}
                            </div>
                            {result.errors.length > 0 && (
                                <details className="mt-4">
                                    <summary className="cursor-pointer text-xs font-semibold uppercase tracking-wider text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                        View error details ({result.errors.length})
                                    </summary>
                                    <ul className="mt-2 max-h-40 space-y-1 overflow-y-auto rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {result.errors.map((e, i) => <li key={i} className="font-mono">{e}</li>)}
                                    </ul>
                                </details>
                            )}
                        </div>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
