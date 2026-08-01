import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import EmptyState from '@/Components/EmptyState';
import StatusPill from '@/Components/StatusPill';
import { Head, Link } from '@inertiajs/react';

/**
 * Phase 16 — `MySubmissions` (was `MyReports`).
 *
 * URL stays at `/my-reports` for backward compatibility (existing nav links
 * + bookmarks keep working). The page name, route Inertia target, and
 * controller method are all renamed. The controller now emits a
 * `submissions` paginated envelope (was `reports`) plus an `activeType:
 * string | null` so the chip row has the active state without an extra
 * round-trip.
 *
 * Click-to-detail: each row's `Preview` and `Date` cells are wrapped in
 * `<Link>` to `/impact-submissions/{id}` — two click targets per row keep
 * the table dense while remaining accessible (pair via aria-label).
 */

type SubmissionRow = {
    id: string;
    type: string;
    data: Record<string, any>;
    fellowship_date_key: string | null;
    impact_cell: { id: string; name: string } | null;
    created_at: string | null;
};

const TYPE_LABEL: Record<string, string> = {
    member: 'Members Data',
    report: 'Cell Report',
    childbirth: 'Childbirth',
    soul: 'Soul Registration',
};

const TYPE_TONE: Record<string, 'info' | 'success' | 'warn' | 'brand' | 'neutral'> = {
    member: 'info',
    report: 'success',
    childbirth: 'warn',
    soul: 'brand',
};

const fileIconPath = (
    <>
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <polyline points="14 2 14 8 20 8" />
        <line x1="9" y1="13" x2="15" y2="13" />
        <line x1="9" y1="17" x2="15" y2="17" />
    </>
);

function preview(s: SubmissionRow): string {
    switch (s.type) {
        case 'member':     return s.data.full_name ?? s.data.name ?? '—';
        case 'report':     return `Attendance: ${s.data.adults ?? 0} adults, ${s.data.children ?? 0} children`;
        case 'childbirth': return s.data.child_name ?? '—';
        case 'soul':       return s.data.full_name ?? s.data.name ?? '—';
        default:           return '—';
    }
}

// Chip row — value `null` represents "All" (no `?type=` query param).
const TYPES: Array<{ value: string | null; label: string }> = [
    { value: null,       label: 'All' },
    { value: 'member',     label: 'Members' },
    { value: 'report',     label: 'Reports' },
    { value: 'childbirth', label: 'Childbirths' },
    { value: 'soul',       label: 'Souls' },
];

function TypeFilterChips({ activeType }: { activeType: string | null }) {
    // Native <Link> semantics — each chip is just a URL-bound filter, NOT
    // a WAI-ARIA tablist (no paired tabpanel; full-page reload). Drop the
    // `role="tablist"` / `role="tab"` / `aria-selected` attributes — they
    // announced "tab" to screen readers for what is really a normal link,
    // which is confusing. `aria-current="page"` reflects the active state
    // to assistive tech without taking on tab semantics.
    const containerClass = 'flex flex-wrap items-center gap-2';

    return (
        <div
            className={containerClass}
            aria-label="Filter submissions by type"
            data-testid="my-submissions-type-filter"
        >
            {TYPES.map((t) => {
                const active = activeType === t.value;
                const slug = t.value ?? 'all';
                const href = t.value === null ? '/my-reports' : `/my-reports?type=${t.value}`;
                return (
                    <Link
                        key={slug}
                        href={href}
                        aria-current={active ? 'page' : undefined}
                        data-testid={`my-submissions-chip-${slug}`}
                        preserveState
                        className={
                            active
                                ? 'inline-flex items-center rounded-full bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800'
                                : 'inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:border-indigo-400 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-indigo-500 dark:hover:text-indigo-300'
                        }
                    >
                        {t.label}
                    </Link>
                );
            })}
        </div>
    );
}

export default function MySubmissions({
    submissions,
    activeRole,
    activeType,
}: {
    submissions: { data: SubmissionRow[] };
    activeRole: string | null;
    activeType: string | null;
}) {
    const showingFilteredType = activeType !== null
        ? TYPE_LABEL[activeType] ?? activeType
        : null;

    return (
        <AdminDashboardLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                            Personal
                        </p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            My Submissions
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Active role: <span className="font-mono">{activeRole ?? '—'}</span>
                            {' · '}
                            {submissions.data.length} submission{submissions.data.length === 1 ? '' : 's'}
                            {showingFilteredType !== null && (
                                <> · filter: <span className="font-semibold text-gray-700 dark:text-gray-300">{showingFilteredType}</span></>
                            )}
                        </p>
                    </div>
                    <Link
                        href={route('impact-submissions.create')}
                        className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        New Submission
                    </Link>
                </div>
            }
        >
            <Head title="My Submissions" />

            <div className="mb-4">
                <TypeFilterChips activeType={activeType} />
            </div>

            {submissions.data.length === 0 ? (
                <EmptyState
                    title={showingFilteredType !== null ? `No ${showingFilteredType} yet` : 'No submissions yet'}
                    description={showingFilteredType !== null
                        ? `Click "All" above to see other submission types, or use "New Submission" to create one of this kind.`
                        : "Your member, report, childbirth, and soul records will appear here. Use \"New Submission\" to log one, or ask an admin to seed sample data."}
                    iconPath={fileIconPath}
                />
            ) : (
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700" data-testid="my-submissions-table">
                            <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Type</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Cell</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Preview</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {submissions.data.map((s) => (
                                    <tr
                                        key={s.id}
                                        className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40"
                                        data-testid={`my-submissions-row-${s.id}`}
                                    >
                                        <td className="px-4 py-3 text-sm">
                                            <StatusPill tone={TYPE_TONE[s.type] ?? 'neutral'} dot>
                                                {TYPE_LABEL[s.type] ?? s.type}
                                            </StatusPill>
                                        </td>
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{s.impact_cell?.name ?? '—'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate">
                                            <Link
                                                href={route('impact-submissions.show', s.id)}
                                                className="hover:text-indigo-600 dark:hover:text-indigo-400"
                                                aria-label={`Open ${TYPE_LABEL[s.type] ?? s.type}: ${preview(s)}`}
                                            >
                                                {preview(s)}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            <Link
                                                href={route('impact-submissions.show', s.id)}
                                                className="hover:text-indigo-600 dark:hover:text-indigo-400"
                                                aria-label={`Open submission dated ${s.created_at?.slice(0, 10) ?? 'unknown'}`}
                                            >
                                                {s.created_at?.slice(0, 10) ?? '—'}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </AdminDashboardLayout>
    );
}
