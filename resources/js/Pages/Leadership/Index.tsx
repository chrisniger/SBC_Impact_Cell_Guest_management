import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import EmptyState from '@/Components/EmptyState';
import LeadershipBoard, { BoardData } from '@/Components/LeadershipBoard';
import { Head } from '@inertiajs/react';

type BoardEntry = { cellId: string; board: BoardData };
type LeadershipPageProps = {
    boards: BoardEntry[];
    activeRole: string | null;
    activeGroup: string | null;
};

/**
 * Phase 08 — /leadership Inertia page.
 *
 * Server-pre-computes per-primary board data and passes it as `initialData`
 * to each `<LeadershipBoard />` so the page makes ZERO additional fetches
 * (would otherwise be 65+ concurrent AJAX calls for an admin — N+1 trap).
 *
 * Renders zero-or-more stacked board sections. When the linked primary has
 * sub-cells, each renders its tile grid. Impact_Leaders sees only the
 * primaries they actually have submissions under (filtered server-side in
 * LeadershipBoardController::index()).
 */
export default function LeadershipIndex({ boards, activeRole }: LeadershipPageProps) {
    const boardCount = boards.length;

    // 2026-08-04 — the Impact_Leaders dashboard no longer renders its inline
    // Leadership Board card; this page is now the single home for that
    // content, so leaders get the familiar "Your leadership tree" heading
    // that used to sit on the dashboard.
    const isLeader = activeRole === 'Impact_Leaders';
    const heading = isLeader
        ? 'Your leadership tree'
        : boardCount === 0
        ? 'No boards available'
        : boardCount === 1
        ? '1 primary cell'
        : `${boardCount} primary cells`;
    return (
        <AdminDashboardLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Leadership Board
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {heading}
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Active role: <span className="font-mono">{activeRole ?? '—'}</span> ·{' '}
                        {isLeader
                            ? 'Engagement status across the primary cell(s) linked to your submissions'
                            : 'Cross-cell submission deltas + primary health roll-ups'}
                    </p>
                </div>
            }
        >
            <Head title="Leadership Board" />

            {boardCount === 0 ? (
                <EmptyState
                    title="No primary cells available"
                    description={
                        activeRole === 'Impact_Leaders'
                            ? 'Once you submit a report targeting any sub-cell, its parent primary cell will appear here.'
                            : 'When the leadership team sets up primary cells with sub-cells, their leadership boards will appear here.'
                    }
                    iconPath={
                        <>
                            <path d="M3 3h18v18H3z" />
                            <path d="M3 9h18M9 21V9" />
                        </>
                    }
                />
            ) : (
                <div
                    className="space-y-12"
                    data-testid="leadership-boards-stack"
                >
                    {boards.map(({ cellId, board }) => (
                        <section
                            key={cellId}
                            className="motion-safe:animate-fade-in"
                            data-testid={`leadership-section-${cellId}`}
                        >
                            <LeadershipBoard cellId={cellId} canView={true} initialData={board} />
                        </section>
                    ))}
                </div>
            )}
        </AdminDashboardLayout>
    );
}
