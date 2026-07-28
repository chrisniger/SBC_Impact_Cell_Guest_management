/**
 * Phase 06d.2 — System Overview panel.
 *
 * 4 progress-bar cards (AdminDashboard variant only):
 *   - DB Size        (controller wraps SHOW TABLE STATUS in try/catch; SQLite fallback)
 *   - Storage Usage  (storage/app + storage/framework aggregate)
 *   - Active Users   (last 7d distinct causers via spatie ActivityLog)
 *   - System Health  (composite: 'Healthy' / 'N issues' based on error log count)
 *
 * data-testid anchors per card (card-* + bar-*) — verified by verify_phase06d2_run.php.
 */

export type SystemOverviewStats = {
    dbSizeMb: number;
    dbSizeLabel: string;
    storageMb: number;
    storageLabel: string;
    activeUsers: number;
    healthLabel: string;
    healthTone: 'success' | 'warn' | 'danger';
};

const BAR_COLOR: Record<'success' | 'warn' | 'danger', string> = {
    success: 'bg-emerald-500 dark:bg-emerald-400',
    warn:    'bg-amber-500 dark:bg-amber-400',
    danger:  'bg-rose-500 dark:bg-rose-400',
};

const TEXT_COLOR: Record<'success' | 'warn' | 'danger', string> = {
    success: 'text-emerald-600 dark:text-emerald-400',
    warn:    'text-amber-600 dark:text-amber-400',
    danger:  'text-rose-600 dark:text-rose-400',
};

export default function SystemOverviewPanel({ stats }: { stats: SystemOverviewStats }) {
    // Map MB to a 0-100 pct of an arbitrary 512 MB saturation point.
    const mbPct = (mb: number): number => Math.min(100, Math.max(0, Math.round((mb / 512) * 100)));

    return (
        <section className="motion-safe:animate-fade-in" data-testid="system-overview-panel">
            <h3 className="mb-3 text-base font-semibold text-gray-900 dark:text-white">System Overview</h3>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {/* Database Size */}
                <div
                    className="rounded-xl border border-gray-200 bg-white p-4 shadow-card dark:border-gray-700 dark:bg-gray-800"
                    data-testid="system-overview-card-db-size"
                >
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Database Size
                    </p>
                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{stats.dbSizeLabel}</p>
                    <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            className={`h-full ${BAR_COLOR.success} transition-all duration-300`}
                            style={{ width: `${mbPct(stats.dbSizeMb)}%` }}
                            data-testid="system-overview-bar-db"
                        />
                    </div>
                </div>

                {/* Storage Usage */}
                <div
                    className="rounded-xl border border-gray-200 bg-white p-4 shadow-card dark:border-gray-700 dark:bg-gray-800"
                    data-testid="system-overview-card-storage"
                >
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Storage Usage
                    </p>
                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{stats.storageLabel}</p>
                    <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            className={`h-full ${stats.storageMb > 400 ? BAR_COLOR.warn : BAR_COLOR.success} transition-all duration-300`}
                            style={{ width: `${mbPct(stats.storageMb)}%` }}
                            data-testid="system-overview-bar-storage"
                        />
                    </div>
                </div>

                {/* Active Users (last 7d distinct causers) */}
                <div
                    className="rounded-xl border border-gray-200 bg-white p-4 shadow-card dark:border-gray-700 dark:bg-gray-800"
                    data-testid="system-overview-card-active-users"
                >
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Active Users (7d)
                    </p>
                    <p className="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                        {stats.activeUsers.toLocaleString()}
                    </p>
                    <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            className={`h-full ${BAR_COLOR.success} transition-all duration-300`}
                            style={{ width: `${Math.min(100, stats.activeUsers)}%` }}
                            data-testid="system-overview-bar-active-users"
                        />
                    </div>
                </div>

                {/* System Health */}
                <div
                    className="rounded-xl border border-gray-200 bg-white p-4 shadow-card dark:border-gray-700 dark:bg-gray-800"
                    data-testid="system-overview-card-health"
                >
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        System Health
                    </p>
                    <p className={`mt-1 text-2xl font-bold ${TEXT_COLOR[stats.healthTone]}`}>
                        {stats.healthLabel}
                    </p>
                    <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            className={`h-full ${BAR_COLOR[stats.healthTone]} transition-all duration-300`}
                            style={{ width: stats.healthTone === 'success' ? '100%' : stats.healthTone === 'warn' ? '60%' : '20%' }}
                            data-testid="system-overview-bar-health"
                        />
                    </div>
                </div>
            </div>
        </section>
    );
}
