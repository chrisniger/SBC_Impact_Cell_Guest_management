import { Link } from '@inertiajs/react';
import StatusPill from '@/Components/StatusPill';

export type LeadershipRollupItem = {
    id: string;
    name: string;
    subCells: number;
    members: number;
    souls: number;
    childbirths: number;
    /** Submitted | Pending | Overdue | New — matches per-tile convention
     *  in LeadershipBoardController::buildBoardData() so the pills render
     *  with identical colors between admin rollup and per-board view. */
    status: string;
    lastReportDate: string | null;
    /** Always points at /leadership (the stacked multi-board Inertia page).
     *  No /leadership-board/{cellId} Inertia route exists today — that URL
     *  serves JSON for the inline LeaderDashboard component. */
    href: string;
};

/**
 * Phase 08+ — Admin leadership tree rollup.
 *
 * Renders one compact card per primary cell on the administrator dashboard.
 * Designed for a 65-primary world: dense-but-readable, no per-tile grid
 * blowup, status rollup color-coded identically to the per-board view in
 * `/leadership`, and a single "View full board →" link per card that lands
 * on the stacked Inertia view at `/leadership`.
 *
 * Server payload shape: `LeadershipRollupItem[]` (one entry per primary).
 * Built by `DashboardController::buildLeadershipRollup()` via 3 bulk queries
 * (no N+1) — even with 65 primaries the rollup costs ≤5 SQL roundtrips.
 *
 * UX decisions kept here for traceability:
 *  - Compact horizontal stat strip rather than a tile grid keeps the
 *    dashboard dense; admin is scanning all primaries, not drilling in.
 *  - LastReportDate shown only when Submitted/Pending/Overdue (null for
 *    "New" primaries → renders an em-dash placeholder).
 *  - Empty state mirrors the design-system pattern from `<EmptyState />`
 *    used elsewhere on the dashboard.
 */
export default function LeadershipRollupWidget({
    items,
}: {
    items: LeadershipRollupItem[];
}) {
    const count = items.length;
    const heading =
        count === 0   ? 'No primary cells yet' :
        count === 1   ? '1 primary cell' :
                        `${count} primary cells`;

    return (
        <section
            className="motion-safe:animate-fade-in space-y-4"
            data-testid="leadership-rollup-root"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Leadership Tree
                    </p>
                    <h3 className="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                        {heading}
                    </h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        Overall leadership roll-up across every primary cell — submit/pending/overdue counts and engagement totals
                    </p>
                </div>
                <Link
                    href={route('leadership.index')}
                    className="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-100 dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-900/60"
                    data-testid="leadership-rollup-view-all"
                >
                    View full board
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>

            {count === 0 ? (
                <div
                    className="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-800"
                    data-testid="leadership-rollup-empty"
                >
                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                        No primary impact cells configured yet
                    </p>
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Once a primary cell is seeded, its leadership roll-up will appear here.
                    </p>
                </div>
            ) : (
                <div
                    className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    data-testid="leadership-rollup-grid"
                >
                    {items.map((it) => <RollupCard key={it.id} item={it} />)}
                </div>
            )}
        </section>
    );
}

function RollupCard({ item }: { item: LeadershipRollupItem }) {
    // Color mapping matches the per-tile convention in LeadershipBoard.tsx so
    // admin and per-board views show identical status colors.
    const statusColor: Record<string, 'success' | 'brand' | 'warn' | 'neutral'> = {
        Submitted: 'success',
        Pending:   'brand',
        Overdue:   'warn',
        New:       'neutral',
    };

    return (
        <Link
            href={item.href}
            className="group flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-card-hover dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-500"
            data-testid={`leadership-rollup-card-${item.id}`}
        >
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <h4 className="truncate text-sm font-semibold text-gray-900 dark:text-white" title={item.name}>
                        {item.name}
                    </h4>
                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {item.subCells === 0
                            ? 'No sub-cells'
                            : item.subCells === 1
                            ? '1 sub-cell'
                            : `${item.subCells} sub-cells`}
                    </p>
                </div>
                <StatusPill tone={statusColor[item.status] ?? 'neutral'}>
                    {item.status}
                </StatusPill>
            </div>

            <div className="grid grid-cols-3 gap-2 border-t border-gray-100 pt-3 text-center dark:border-gray-700">
                <Stat label="Members"     value={item.members} />
                <Stat label="Souls"       value={item.souls} />
                <Stat label="Childbirths" value={item.childbirths} />
            </div>

            <div className="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                <span>
                    {item.lastReportDate
                        ? `Last report: ${item.lastReportDate.slice(0, 10)}`
                        : 'No reports yet'}
                </span>
                <span className="inline-flex items-center gap-1 font-medium text-indigo-600 opacity-0 transition-opacity group-hover:opacity-100 dark:text-indigo-400">
                    View board
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </span>
            </div>
        </Link>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div>
            <p className="text-base font-semibold tabular-nums text-gray-900 dark:text-white">
                {value}
            </p>
            <p className="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {label}
            </p>
        </div>
    );
}
