import { ReactNode } from 'react';
import EmptyState from '@/Components/EmptyState';

/**
 * Phase 13+ — premium dashboard table chrome.
 *
 * Replaces the ad-hoc inline `<table>` markup that was repeated six times
 * across Dashboard.tsx (Officer / Team / Leader Assigned Guests / Zonal
 * Submissions / etc.). Visual direction:
 *   - closer-spaced rows (`px-3 py-2.5` vs the older `px-4 py-3`)
 *   - mild off-white/dark headers (no longer competing with row content)
 *   - row hover goes tinted (indigo wash on light, gray wash on dark)
 *   - subtle border-b only on rows (no internal vertical dividers)
 *
 * The actual `<table>` keeps literal `motion-safe:animate-fade-in`,
 * `shadow-card`, and the standard card chrome so `verify_phase06b_run.php`
 * token assertions still match.
 *
 * All existing `data-testid` hooks remain verbatim:
 *   - outer table testid         → `tableTestId` prop
 *   - per-row testid             → `rowTestId(g)` callback
 *   - empty row testid           → `emptyTestId` prop
 *
 * Hover row is `hover:bg-indigo-50/40 dark:hover:bg-gray-700/40` —
 * matches the pre-extraction pattern from Dashboard.tsx so the keyboard
 * and mouse states remain identical.
 */
export type Column<T> = {
    /** Header label (rendered uppercase via tracking, NOT via text-transform so the DOM is plain). */
    header: ReactNode;
    /** Cell renderer — receives the row + index. */
    cell: (row: T, idx: number) => ReactNode;
    /** Optional className override for every cell in this column. */
    cellClassName?: string;
    /** Optional className override for every header in this column. */
    headerClassName?: string;
    /** Optional width hint (e.g. `'w-32'`, `'w-1/3'`). */
    width?: string;
    /** Right-align the column (action slots). */
    align?: 'left' | 'right' | 'center';
};

type Props<T> = {
    /** Column descriptors in order. */
    columns: Column<T>[];
    /** Rows. Empty array → renders empty state in a card. */
    rows: T[];
    /** Whether the table has at least one row visible. */
    /** Outer table testid (e.g. 'guests-table', 'assigned-guests-table'). */
    tableTestId?: string;
    /** Per-row testid callback. Returns full string per row. */
    rowTestId?: (row: T, idx: number) => string | undefined;
    /** Empty-row placeholder cell testid (when rows.length === 0). */
    emptyTestId?: string;
    /** Empty-state title (used when rows.length === 0). */
    emptyTitle?: string;
    /** Empty-state description. */
    emptyDescription?: string;
    /** Empty-state icon path (24x24 Heroicons-style). */
    emptyIconPath?: ReactNode;
    /** Optional className to wrap card chrome in (Padding, etc). */
    className?: string;
    /** Compact mode slightly trims padding for dense data tables. */
    compact?: boolean;
    /** Optional className for the table element itself. */
    tableClassName?: string;
};

export default function DashboardTable<T>({
    columns,
    rows,
    tableTestId,
    rowTestId,
    emptyTestId,
    emptyTitle,
    emptyDescription,
    emptyIconPath,
    className = '',
    compact = false,
    tableClassName = '',
}: Props<T>) {
    const isEmpty = rows.length === 0;

    // Compact = `py-2`, normal = `py-2.5`. Both use `px-3` for horizontal
    // breathing (previous chrome used px-4 which felt loose against the
    // tighter premium chrome we want).
    const cellPad = compact ? 'px-3 py-2' : 'px-3 py-2.5';

    if (isEmpty && (emptyTitle || emptyDescription)) {
        // Render an EmptyState when caller passes the empty copy. We still
        // keep the table chrome aside (no <table> for an empty set) so the
        // empty-state still wins visually inside the card.
        return (
            <div
                className={`motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800 ${className}`}
                data-testid={tableTestId ? `${tableTestId}-empty` : undefined}
            >
                <EmptyState
                    title={emptyTitle ?? '—'}
                    description={emptyDescription}
                    iconPath={emptyIconPath}
                />
            </div>
        );
    }

    return (
        <div
            data-card="dashboard-table"
            className={`motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800 ${className}`}
        >
            <div className="overflow-x-auto">
                <table
                    data-testid={tableTestId}
                    className={`min-w-full divide-y divide-gray-200 border-collapse dark:divide-gray-700 ${tableClassName}`}
                >
                    <thead className="bg-[#FAFBFD] dark:bg-gray-900/40">
                        <tr>
                            {columns.map((c, i) => (
                                <th
                                    key={i}
                                    className={`${cellPad} text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 ${c.headerClassName ?? ''} ${c.width ?? ''}`}
                                >
                                    {c.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700/70">
                        {rows.map((row, idx) => {
                            const testId = rowTestId?.(row, idx);
                            return (
                                <tr
                                    key={testId ?? (row as any).id ?? idx}
                                    data-testid={testId}
                                    className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40"
                                >
                                    {columns.map((c, i) => (
                                        <td
                                            key={i}
                                            className={`${cellPad} text-sm ${alignClass(c.align)} ${
                                                i === 0 && !c.align
                                                    ? 'font-medium text-gray-900 dark:text-gray-100'
                                                    : 'text-gray-700 dark:text-gray-300'
                                            } ${c.cellClassName ?? ''}`}
                                        >
                                            {c.cell(row, idx)}
                                        </td>
                                    ))}
                                </tr>
                            );
                        })}
                        {rows.length === 0 && emptyTestId && (
                            <tr data-testid={emptyTestId}>
                                <td
                                    colSpan={columns.length}
                                    className={`${cellPad} py-10 text-center text-sm text-gray-500 dark:text-gray-400`}
                                >
                                    No data
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function alignClass(a: 'left' | 'right' | 'center' | undefined): string {
    if (a === 'right') return 'text-right';
    if (a === 'center') return 'text-center';
    return 'text-left';
}
