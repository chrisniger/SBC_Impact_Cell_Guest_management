import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useRef, useState } from 'react';

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
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                CSV Import
            </h2>
        }>
            <Head title="CSV Import" />
            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            <div
                                onClick={() => inputRef.current?.click()}
                                className="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-6 py-12 hover:border-red-400 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-900 dark:hover:border-red-500">
                                {file ? (
                                    <p className="text-sm font-medium text-gray-800 dark:text-gray-200">{file.name} ({(file.size / 1024).toFixed(1)} KB)</p>
                                ) : (
                                    <>
                                        <p className="text-base font-medium text-gray-700 dark:text-gray-300">Drop CSV file here or click to browse</p>
                                        <p className="mt-1 text-xs text-gray-500">Headers: guest_name, phone, email, event, source</p>
                                    </>
                                )}
                                <input ref={inputRef} type="file" accept=".csv,.txt" className="hidden" onChange={e => setFile(e.target.files?.[0] ?? null)} />
                            </div>
                            {file && (
                                <div className="mt-4 flex justify-end">
                                    <button onClick={handleUpload} disabled={loading}
                                        className="rounded-md bg-gray-800 px-6 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-50 dark:bg-gray-200 dark:text-gray-800">
                                        {loading ? 'Uploading…' : 'Upload & Import'}
                                    </button>
                                </div>
                            )}
                            {result && (
                                <div className="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                                    <p className="text-sm font-medium">Created: <span className="text-green-600">{result.created}</span>, Skipped: <span className="text-amber-600">{result.skipped}</span></p>
                                    {result.errors.length > 0 && (
                                        <details className="mt-2">
                                            <summary className="cursor-pointer text-xs text-gray-500 hover:text-gray-700">Details ({result.errors.length})</summary>
                                            <ul className="mt-2 max-h-40 space-y-1 overflow-y-auto text-xs text-gray-600">
                                                {result.errors.map((e, i) => <li key={i}>{e}</li>)}
                                            </ul>
                                        </details>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
