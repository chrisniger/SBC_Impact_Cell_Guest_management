import {
    Area,
    AreaChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

/**
 * Phase 06d.1 — Overview Analytics chart panel (recharts AreaChart).
 *
 * Lazy-loaded via React.lazy(() => import('@/Components/OverviewAnalytics'))
 * in Dashboard.tsx so the ~150 kB recharts bundle is held back until the
 * Admin Dashboard mounts. The `<Suspense>` fallback uses the same fixed
 * height as this component's outer wrapper to prevent Cumulative Layout
 * Shift on first render.
 *
 * Props:
 *   - series:  per-metric array of counts (one entry per `labels` bucket).
 *              Keys must match METRIC_META below.
 *   - labels:  X-axis bucket labels (oldest → newest).
 *   - height:  optional fixed height for the ResponsiveContainer. Default 320px.
 *
 * data-testid anchors:
 *   - overview-analytics-section   — outer card
 *   - overview-analytics-chart-canvas — chart canvas wrapper
 *   - overview-analytics-series-{kebab-metric} — each rendered recharts Area
 */

type Props = {
    series: Record<string, number[]>;
    labels: string[];
    height?: number;
};

const METRIC_META: Array<{ key: string; label: string; color: string }> = [
    { key: 'totalGuests',      label: 'Guests',      color: '#6366f1' }, // indigo-500
    { key: 'totalCalls',       label: 'Contacts',    color: '#10b981' }, // emerald-500
    { key: 'totalSubmissions', label: 'Submissions', color: '#f59e0b' }, // amber-500
    { key: 'totalUsers',       label: 'Users',       color: '#3b82f6' }, // blue-500
];

export default function OverviewAnalytics({ series, labels, height = 320 }: Props) {
    // Project the per-metric series arrays into recharts' row-shape:
    //   [{ label: 'Jul 14', Guests: 12, Contacts: 3, Submissions: 5, Users: 1 }, ...]
    const data = labels.map((label, idx) => {
        const row: Record<string, string | number> = { label };
        METRIC_META.forEach((m) => {
            const arr = series[m.key] ?? [];
            row[m.label] = idx < arr.length ? arr[idx] ?? 0 : 0;
        });
        return row;
    });

    const unit = labels.length > 31 ? 'monthly' : 'daily';

    return (
        <div
            className="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-card dark:border-gray-700 dark:bg-gray-800"
            data-testid="overview-analytics-section"
        >
            <div className="mb-2 flex items-center justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                        Overview Analytics
                    </h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        Trend across {labels.length} {unit} buckets
                    </p>
                </div>
            </div>
            <div
                className="w-full"
                data-testid="overview-analytics-chart-canvas"
                style={{ minHeight: height, height }}
            >
                <ResponsiveContainer width="100%" height={height}>
                    <AreaChart data={data} margin={{ top: 10, right: 10, left: 0, bottom: 0 }}>
                        <defs>
                            {METRIC_META.map((m) => (
                                <linearGradient
                                    id={`grad-${m.key}`}
                                    key={m.key}
                                    x1="0" y1="0" x2="0" y2="1"
                                >
                                    <stop offset="0%" stopColor={m.color} stopOpacity={0.4} />
                                    <stop offset="100%" stopColor={m.color} stopOpacity={0} />
                                </linearGradient>
                            ))}
                        </defs>
                        <CartesianGrid
                            strokeDasharray="3 3"
                            stroke="#e5e7eb"
                            className="dark:opacity-30"
                        />
                        <XAxis
                            dataKey="label"
                            tick={{ fontSize: 11, fill: '#6b7280' }}
                            stroke="#9ca3af"
                        />
                        <YAxis
                            tick={{ fontSize: 11, fill: '#6b7280' }}
                            stroke="#9ca3af"
                            allowDecimals={false}
                        />
                        <Tooltip
                            contentStyle={{ borderRadius: 8, border: '1px solid #e5e7eb', fontSize: 12 }}
                            labelStyle={{ fontWeight: 600 }}
                        />
                        <Legend wrapperStyle={{ fontSize: 12 }} />
                        {METRIC_META.map((m) => (
                            <Area
                                key={m.key}
                                type="monotone"
                                dataKey={m.label}
                                stroke={m.color}
                                fill={`url(#grad-${m.key})`}
                                strokeWidth={2}
                                isAnimationActive={true}
                                data-testid={`overview-analytics-series-${m.label.toLowerCase()}`}
                            />
                        ))}
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
