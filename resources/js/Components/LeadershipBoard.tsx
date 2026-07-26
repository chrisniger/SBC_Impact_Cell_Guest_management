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
}

interface BoardData {
    primaryCell: { id: string; name: string };
    tiles: Tile[];
    totals: { members: number; souls: number; childbirths: number; subCells: number };
}

export default function LeadershipBoard({ cellId, canView }: { cellId: string; canView: boolean }) {
    const [data, setData] = useState<BoardData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!canView) { setLoading(false); return; }
        setLoading(true);
        fetch(`/leadership-board/${cellId}`)
            .then(r => r.ok ? r.json() : Promise.reject('Failed to load'))
            .then(d => { setData(d); setLoading(false); })
            .catch(e => { setError(String(e)); setLoading(false); });
    }, [cellId, canView]);

    if (!canView) return null;
    if (loading) return <div className="p-4 text-sm text-gray-500">Loading board…</div>;
    if (error) return <div className="p-4 text-sm text-red-600">{error}</div>;
    if (!data || data.tiles.length === 0) return null;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
                    {data.primaryCell.name} — Leadership Board
                </h3>
                <div className="flex gap-4 text-xs text-gray-500">
                    <span>{data.totals.subCells} sub-cells</span>
                    <span>{data.totals.members} members</span>
                    <span>{data.totals.souls} souls</span>
                    <span>{data.totals.childbirths} childbirths</span>
                </div>
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {data.tiles.map(t => <SubCellTile key={t.id} tile={t} />)}
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
            {tile.lastReportDate && (
                <p className="mt-2 text-xs text-gray-400">Last report: {tile.lastReportDate.slice(0, 10)}</p>
            )}
        </div>
    );
}
