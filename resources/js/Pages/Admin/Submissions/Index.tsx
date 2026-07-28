import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import { Head, Link } from '@inertiajs/react';

/**
 * Phase 06d.0 — STUB alias page.
 *
 * The 'Submissions' sidebar nav entry could link directly to the existing
 * /impact-submissions.index route, but doing so would skip the admin chrome
 * (sidebar collapses, header band changes). This stub gives the entry a
 * real landing that explains where to find submissions + links back to the
 * canonical list.
 */
export default function AdminSubmissionsIndex() {
    return (
        <AdminDashboardLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Administrator · Submissions
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Submissions
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Browse all Member Data, Report, Childbirth, and Soul submissions system-wide.
                    </p>
                </div>
            }
        >
            <Head title="Submissions · Admin" />
            <div
                className="mx-auto max-w-2xl rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-card dark:border-gray-700 dark:bg-gray-800"
                data-testid="admin-submissions-stub"
            >
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-purple-50 text-purple-600 dark:bg-purple-900/40 dark:text-purple-300">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                    </svg>
                </div>
                <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-white">View all submissions</h3>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    The canonical submissions list lives at <code className="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-700">/impact-submissions</code> (with filters by type, cell, and date).
                </p>
                <div className="mt-6 flex items-center justify-center gap-3">
                    <Link
                        href={route('impact-submissions.index')}
                        className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
                        data-testid="admin-submissions-open-list"
                    >
                        Open submissions list →
                    </Link>
                    <Link
                        href={route('dashboard')}
                        className="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        data-testid="admin-submissions-back-to-dashboard"
                    >
                        ← Back to Dashboard
                    </Link>
                </div>
            </div>
        </AdminDashboardLayout>
    );
}
