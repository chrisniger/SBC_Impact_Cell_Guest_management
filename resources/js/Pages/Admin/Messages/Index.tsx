import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import { Head, Link } from '@inertiajs/react';

/**
 * Phase 06d.0 — STUB page.
 *
 * 'Messages' is an inbox surface for the pastor / leadership team.
 * Phase 09 covers SMTP notifications + read receipts; the inbox UI
 * ships in a later round aligned with that work.
 */
export default function AdminMessagesIndex() {
    return (
        <AdminDashboardLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Administrator · Messages
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Messages
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Inbox for cross-cell announcements and follow-up threads.
                    </p>
                </div>
            }
        >
            <Head title="Messages · Admin" />
            <div
                className="mx-auto max-w-2xl rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-card dark:border-gray-700 dark:bg-gray-800"
                data-testid="admin-messages-stub"
            >
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6" aria-hidden="true">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
                    </svg>
                </div>
                <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Coming soon</h3>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Messages inbox ships after Phase 09 (SMTP notifications). Today, in-cell communication happens via the follow-up status flow on individual guest records.
                </p>
                <Link
                    href={route('dashboard')}
                    className="mt-6 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
                    data-testid="admin-messages-back-to-dashboard"
                >
                    ← Back to Dashboard
                </Link>
            </div>
        </AdminDashboardLayout>
    );
}
