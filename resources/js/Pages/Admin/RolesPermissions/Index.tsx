import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import { Head, Link } from '@inertiajs/react';

/**
 * Phase 06d.0 — STUB page.
 *
 * 'Roles & Permissions' CRUD ships in a later phase. The current role
 * definitions live in `app/Support/RoleHelper.php` (single source of truth);
 * the admin-facing edit surface is on the deck for Phase 06e+.
 *
 * See app/Support/RoleHelper.php for the canonical role matrix + the
 * 3 user groups (impactCell / followUpOfficer / followUpTeam).
 */
export default function AdminRolesPermissionsIndex() {
    return (
        <AdminDashboardLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Administrator · Roles & Permissions
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Roles & Permissions
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Configure the 3 user groups and the column-edit matrix.
                    </p>
                </div>
            }
        >
            <Head title="Roles & Permissions · Admin" />
            <div
                className="mx-auto max-w-2xl rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center shadow-card dark:border-gray-700 dark:bg-gray-800"
                data-testid="admin-roles-permissions-stub"
            >
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6" aria-hidden="true">
                        <path d="M12 2l3 6 6 1-4.5 4 1 6L12 16l-5.5 3 1-6L3 9l6-1z" />
                    </svg>
                </div>
                <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Coming soon</h3>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Group / role / permission editor ships in a later phase. Role matrix today is read-only via <code className="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-700">app/Support/RoleHelper.php</code>.
                </p>
                <Link
                    href={route('dashboard')}
                    className="mt-6 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700"
                    data-testid="admin-roles-permissions-back-to-dashboard"
                >
                    ← Back to Dashboard
                </Link>
            </div>
        </AdminDashboardLayout>
    );
}
