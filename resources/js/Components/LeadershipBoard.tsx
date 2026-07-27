import { useEffect, useState } from 'react';
import StatusPill from '@/Components/StatusPill';

interface Tile {
    id: string;
    name: string;
    phone: string | null;
    membersCount: number;
    soulsCount: number;
    childbirthsCount: number;
    reportStatus: string;
    lastReportDate: string | null;
    /** Phase 08 — leader lookup deferred until users.impact_cell_id ships. Always null today. */
    leaderFullName: string | null;
    /** Phase 08 — for now points to the sub-cell's own phone (cell leader is the assigned cell user). */
    leaderPhone: string | null;
}

export interface BoardData {
    primaryCell: { id: string; name: string };
    tiles: Tile[];
    totals: { members: number; souls: number; childbirths: number; subCells: number };
    /** Phase 08 — envelope extension. */
    generatedAt?: string;
    fromCache?: boolean;
    cacheKey?: string;
}

/**
 * Phase 08 — accepts optional `initialData` to avoid N+1 fetches when the
 * parent (e.g. `/leadership` stacked view) has already pre-computed the
 * board server-side via `LeadershipBoardController::index()`.
 *
 * Backwards-compatible: when `initialData` is omitted, falls back to
 * `fetch('/leadership-board/' + cellId)` so existing call sites
 * (LeaderDashboard inline use) keep working unchanged.
 */
export default function LeadershipBoard({
    cellId,
    canView,
    initialData = null,
}: {
    cellId: string;
    canView: boolean;
    initialData?: BoardData | null;
}) {
    const [data, setData] = useState<BoardData | null>(initialData);
    const [loading, setLoading] = useState<boolean>(initialData == null);
    const [error, setError] = useState<string>('');

    useEffect(() => {
        if (!canView) { setLoading(false); return; }
        // Skip fetch when the parent already provided server-computed data
        // (Phase 08 stacked-multi-board view).
        if (initialData != null) {
            setData(initialData);
            setLoading(false);
            return;
        }
        setLoading(true);
        fetch(`/leadership-board/${cellId}`)
            .then(r => r.ok ? r.json() : Promise.reject('Failed to load'))
            .then((d: BoardData) => { setData(d); setLoading(false); })
            .catch((e) => { setError(String(e)); setLoading(false); });
    }, [cellId, canView, initialData]);

    if (!canView) return null;
    if (loading) return <div className="p-4 text-sm text-gray-500">Loading board…</div>;
    if (error) return <div className="p-4 text-sm text-red-600">{error}</div>;
    if (!data || data.tiles.length === 0) return null;

    return (
        <div className="space-y-4" data-testid={`leadership-board-${cellId}`}>
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
                    {data.primaryCell.name} — Leadership Board
                </h3>
                <div className="flex gap-4 text-xs text-gray-500">
                    <span>{data.totals.subCells} sub-cells</span>
                    <span>{data.totals.members} members</span>
                    <span>{data.totals.souls} souls</span>
                    <span>{data.totals.childbirths} childbirths</span>
                    {data.fromCache === true && (
                        <span className="rounded-full bg-emerald-100 px-2 py-0.5 font-mono text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            cached
                        </span>
                    )}
                </div>
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {data.tiles.map((t) => <SubCellTile key={t.id} tile={t} />)}
            </div>
        </div>
    );
}

function SubCellTile({ tile }: { tile: Tile }) {
    const statusColor: Record<string, 'success' | 'brand' | 'warn' | 'neutral'> = {
        Submitted: 'success',
        Pending: 'brand',
        Overdue: 'warn',
        New: 'neutral',
    };

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:shadow-md hover:ring-1 hover:ring-red-300 dark:border-gray-700 dark:bg-gray-800">
            <div className="mb-2 flex items-start justify-between">
                <div>
                    <h4 className="font-medium text-gray-900 dark:text-gray-100">{tile.name}</h4>
                    {tile.phone && <p className="text-xs text-gray-500">{tile.phone}</p>}
                </div>
                <StatusPill tone={statusColor[tile.reportStatus] ?? 'neutral'}>{tile.reportStatus}</StatusPill>
            </div>
            <div className="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
                <div>
                    <p className="text-lg font-semibold text-gray-800 dark:text-gray-200">{tile.membersCount}</p>
                    <p className="text-gray-500">Members</p>
                </div>
                <div>
                    <p className="text-lg font-semibold text-gray-800 dark:text-gray-200">{tile.soulsCount}</p>
                    <p className="text-gray-500">Souls</p>
                </div>
                <div>
                    <p className="text-lg font-semibold text-gray-800 dark:text-gray-200">{tile.childbirthsCount}</p>
                    <p className="text-gray-500">Childbirths</p>
                </div>
            </div>
            {/* Phase 08 — leader contact block (fullName null until users.impact_cell_id ships) */}
            {(tile.leaderFullName !== null || tile.leaderPhone !== null) && (
                <div className="mt-3 border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-gray-700">
                    {tile.leaderFullName && (
                        <p>Leader: <span className="font-medium text-gray-700 dark:text-gray-300">{tile.leaderFullName}</span></p>
                    )}
                    {tile.leaderPhone && <p className="font-mono">{tile.leaderPhone}</p>}
                </div>
            )}
            {tile.lastReportDate && (
                <p className="mt-2 text-xs text-gray-400">Last report: {tile.lastReportDate.slice(0, 10)}</p>
            )}
        </div>
    );
}
