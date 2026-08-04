import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
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

const downloadIconPath = (
    <>
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <polyline points="7 10 12 15 17 10" />
        <line x1="12" y1="15" x2="12" y2="3" />
    </>
);

const exportIconPath = (
    <>
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <polyline points="7 10 12 15 17 10" />
        <line x1="12" y1="15" x2="12" y2="3" />
    </>
);

// Phase 10e — 'Import to test' button glyph (play inside a circle).
const testImportIconPath = (
    <>
        <circle cx="12" cy="12" r="10" />
        <polygon points="10 8 16 12 10 16 10 8" />
    </>
);

// Phase 10d — one per-template export link. Mirrors the sample grid above:
// each export streams ALL guests using the SAME canonical columns as the
// matching import sample, so a saved export re-imports cleanly with that
// template. The server (CsvExportController::export?template=) is admin-only
// for template exports (the column sets include group-owned fields).
const csvExports = [
    { key: 'default', label: 'Default', description: 'Base fields only (guest_name, phone, email, event, source)' },
    { key: 'officer', label: 'Officer', description: 'contacted_status + visited' },
    { key: 'team',    label: 'Team',    description: 'follow_up_status + follow_up_contacts' },
    { key: 'impact',  label: 'Impact',  description: 'impact_status + nearest_impact_cell_id' },
];

// Phase 10c — one sample download per existing CSV import template. The
// server (CsvImportController::sample) streams a header row + one example
// row using canonical column names, so a saved sample re-imports as-is.
const csvSamples = [
    { key: 'default', label: 'Default',    description: 'Base fields (guest_name, phone, email, event, source)' },
    { key: 'officer', label: 'Officer',    description: 'Adds contacted_status + visited' },
    { key: 'team',    label: 'Team',       description: 'Adds follow_up_status + follow_up_contacts' },
    { key: 'impact',  label: 'Impact',     description: 'Adds impact_status + nearest_impact_cell_id' },
];

export default function CsvImport() {
    const [file, setFile] = useState<File | null>(null);
    const [template, setTemplate] = useState<string>('');
    const [result, setResult] = useState<{ created: number; skipped: number; errors: string[] } | null>(null);
    const [loading, setLoading] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    // Phase 10c — the raw fetch must carry the CSRF token itself (axios would
    // inject X-XSRF-TOKEN automatically, but fetch does not). Laravel's
    // XSRF-TOKEN cookie is readable (not httpOnly) for exactly this purpose.
    const csrfHeader = (): Record<string, string> => {
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {};
    };

    const submitCsv = async (csvFile: File, tpl: string) => {
        setLoading(true);
        setResult(null);
        const formData = new FormData();
        formData.append('csv', csvFile);
        if (tpl) formData.append('template', tpl);
        try {
            const res = await fetch('/csv/import', { method: 'POST', body: formData, headers: csrfHeader() });
            const json = await res.json();
            setResult(json);
        } catch {
            setResult({ created: 0, skipped: 0, errors: ['Upload failed.'] });
        }
        setLoading(false);
    };

    const handleUpload = async () => {
        if (!file) return;
        await submitCsv(file, template);
    };

    // Phase 10e — 'Import to test': fetch the sample for a template, wrap it
    // in a File, and push it through the exact same import pipeline so admins
    // can preview the flow without preparing a file. The example row's phone
    // is randomized so repeated test imports always show Created: 1 instead
    // of tripping the duplicate-by-phone skip.
    const handleSampleImport = async (key: string) => {
        if (loading) return;
        const tpl = key === 'default' ? '' : key;
        try {
            const res = await fetch(route('csv.sample', key === 'default' ? '' : key));
            if (!res.ok) throw new Error('sample fetch failed');
            let csvText = await res.text();
            // Sample phone is 11 digits (08 + 9 more); swap it for a fresh one
            // so repeat test imports never collide on the duplicate-by-phone rule.
            csvText = csvText.replace(/08\d{9}/, '08' + Math.floor(100000000 + Math.random() * 899999999));
            const sampleFile = new File([csvText], `sample-${key}.csv`, { type: 'text/csv' });
            setTemplate(tpl);
            setFile(sampleFile);
            await submitCsv(sampleFile, tpl);
        } catch {
            setResult({ created: 0, skipped: 0, errors: ['Sample import failed.'] });
        }
    };

    return (
        <AdminDashboardLayout header={
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
                <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="card-csv-import">
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

                        <div className="mt-4 flex items-center gap-3">
                            <label htmlFor="csv-template" className="text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">
                                Template
                            </label>
                            <select
                                id="csv-template"
                                value={template}
                                onChange={e => setTemplate(e.target.value)}
                                className="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                data-testid="csv-template-select"
                            >
                                <option value="">— default (base fields only) —</option>
                                <option value="officer">Officer (officer-group fields)</option>
                                <option value="team">Team (team-group fields)</option>
                                <option value="impact">Impact (impact-group fields)</option>
                            </select>
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

                <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="card-csv-exports">
                    <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                {exportIconPath}
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Export CSV</h3>
                    </header>
                    <div className="p-6">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Download all guests as a CSV using the same column set as each import template — headers match the samples, so an exported file can be edited and re-imported as-is.
                        </p>
                        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {csvExports.map((s) => (
                                <a
                                    key={s.key}
                                    href={route('csv.export', { template: s.key })}
                                    download
                                    data-testid={`csv-export-${s.key}`}
                                    className="group flex flex-col items-start gap-2 rounded-xl border border-gray-200 bg-gray-50/50 p-4 transition-all duration-200 hover:border-blue-300 hover:bg-blue-50/40 hover:shadow-card-hover dark:border-gray-700 dark:bg-gray-800/60 dark:hover:border-blue-600 dark:hover:bg-blue-900/20"
                                >
                                    <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 transition-transform group-hover:scale-110 dark:bg-blue-900/40 dark:text-blue-300" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
                                            {exportIconPath}
                                        </svg>
                                    </span>
                                    <span className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                        {s.label}
                                    </span>
                                    <span className="text-xs leading-snug text-gray-500 dark:text-gray-400">
                                        {s.description}
                                    </span>
                                </a>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="card-csv-samples">
                    <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                {downloadIconPath}
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Download CSV Samples</h3>
                    </header>
                    <div className="p-6">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Grab a ready-made sample file for each import template — headers are pre-filled with one example row. Save it, replace the example with your guests, then upload above — or hit <span className="font-semibold text-indigo-600 dark:text-indigo-400">Import to test</span> to run a sample through the importer right now.
                        </p>
                        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {csvSamples.map((s) => (
                                <div
                                    key={s.key}
                                    className="group flex flex-col rounded-xl border border-gray-200 bg-gray-50/50 p-4 transition-all duration-200 hover:border-indigo-300 hover:bg-indigo-50/40 hover:shadow-card-hover dark:border-gray-700 dark:bg-gray-800/60 dark:hover:border-indigo-600 dark:hover:bg-indigo-900/20"
                                >
                                    <a
                                        href={route('csv.sample', s.key === 'default' ? '' : s.key)}
                                        download
                                        data-testid={`csv-sample-${s.key}`}
                                        className="flex flex-col items-start gap-2"
                                    >
                                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition-transform group-hover:scale-110 dark:bg-emerald-900/40 dark:text-emerald-300" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4">
                                                {downloadIconPath}
                                            </svg>
                                        </span>
                                        <span className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                            {s.label}
                                        </span>
                                        <span className="text-xs leading-snug text-gray-500 dark:text-gray-400">
                                            {s.description}
                                        </span>
                                    </a>
                                    <button
                                        type="button"
                                        onClick={() => handleSampleImport(s.key)}
                                        disabled={loading}
                                        className="mt-3 inline-flex items-center justify-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-900/70"
                                        data-testid={`csv-sample-test-${s.key}`}
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                            {testImportIconPath}
                                        </svg>
                                        Import to test
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {result && (
                    <section className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="card-csv-result">
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
        </AdminDashboardLayout>
    );
}
