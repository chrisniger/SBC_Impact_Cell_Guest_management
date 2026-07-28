import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import { Head, Link } from '@inertiajs/react';

/**
 * Phase 06d.0 — STUB page.
 *
 * 'Analytics' is the cross-cell trends overview with recharts AreaChart +
 * date-range filter. The real chart ships in Phase 06d.1.
 *
 * This stub exists so the sidebar nav entry has a real link target today.
 */
export default function AdminAnalyticsIndex() {
    return (
        <AdminDashboardLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Administrator · Analytics
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Analytics
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Cross-cell trends, submission deltas, and KPI time-series.
                    </p>
                </div>
            }
        >
            <Head title="Analytics · Admin" />
            <div
                className="mx-auto max-w-2xl rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-card dark:border-gray-700 dark:bg-gray-800"
                data-testid="admin-analytics-stub"
            >
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6" aria-hidden="true">
                        <circle cx="11" cy="11" r="7" />
                        <line x1="20" y1="20" x2="16.65" y2="16.65" />
                    </svg>
                </div>
                <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Coming soon — Phase 06d.1</h3>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    The Overview Analytics panel with recharts AreaChart + Today/Week/Month/Year range ships in the next sub-phase.
                </p>
                <Link
                    href={route('dashboard')}
                    className="mt-6 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
                    data-testid="admin-analytics-back-to-dashboard"
                >
                    ← Back to Dashboard
                </Link>
            </div>
        </AdminDashboardLayout>
    );
}
