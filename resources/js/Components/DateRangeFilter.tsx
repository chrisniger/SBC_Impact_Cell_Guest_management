import { Popover, Transition } from '@headlessui/react';
import { Fragment, useState } from 'react';
import { router } from '@inertiajs/react';

type DateRangeKey = 'today' | 'week' | 'month' | 'year' | 'custom';

type Props = {
    /** Current range key (URL-bound). Server defaults to 'week'. */
    rangeKey: string;
    /** ISO date string for custom-from (when rangeKey === 'custom'). */
    customFrom?: string | null;
    /** ISO date string for custom-to (when rangeKey === 'custom'). */
    customTo?: string | null;
    /**
     * Phase 34 — target route for the range change. Defaults to '/dashboard';
     * the Admin Analytics page passes '/admin/analytics' so the filter stays
     * on the Analytics page instead of bouncing back to the dashboard.
     */
    target?: string;
};

const PRESETS: Array<{ key: DateRangeKey; label: string; testid: string }> = [
    { key: 'today', label: 'Today', testid: 'date-range-today' },
    { key: 'week',  label: 'Week',  testid: 'date-range-week' },
    { key: 'month', label: 'Month', testid: 'date-range-month' },
    { key: 'year',  label: 'Year',  testid: 'date-range-year' },
];

/**
 * Phase 06d.1 — Date range filter for the Admin Dashboard Overview Analytics.
 *
 * - 5 presets: Today (24h hourly) / Week (7d daily) / Month (30d daily) /
 *   Year (12m monthly) / Custom (date-picker popover).
 * - Updates URL via Inertia router.get('/dashboard', { range, from?, to? });
 *   the dashboard controller (DashboardController::parseRange()) reads
 *   ?range= and computes the matching bucket array.
 * - Eager-mounted (NOT lazy-loaded) so the filter UI is immediately
 *   interactive; the chart bundle (recharts) is lazy-load only via
 *   Dashboard.tsx's React.lazy boundary.
 *
 * data-testid anchor points:
 *   - date-range-filter      — outer wrapper
 *   - date-range-{preset}    — 4 preset buttons (today/week/month/year)
 *   - date-range-custom      — Custom popover trigger
 *   - date-range-custom-panel — open popover body (HeadlessUI Popover.Panel)
 *   - date-range-custom-from / -to — date inputs
 *   - date-range-custom-apply       — apply button
 */
function pushRange(target: string, range: DateRangeKey, from?: string | null, to?: string | null) {
    const params: Record<string, string> = { range };
    if (range === 'custom' && from && to && from <= to) {
        params.from = from;
        params.to = to;
    }
    router.get(target, params, { preserveState: true, preserveScroll: true });
}

export default function DateRangeFilter({ rangeKey, customFrom, customTo, target = '/dashboard' }: Props) {
    const [from, setFrom] = useState<string>(customFrom ?? '');
    const [to, setTo]     = useState<string>(customTo ?? '');
    const isCustom = rangeKey === 'custom';

    return (
        <div
            className="inline-flex flex-wrap items-center gap-2"
            data-testid="date-range-filter"
        >
            {PRESETS.map((preset) => {
                const isActive = rangeKey === preset.key;
                return (
                    <button
                        key={preset.key}
                        type="button"
                        onClick={() => pushRange(target, preset.key)}
                        data-testid={preset.testid}
                        className={
                            'rounded-md border px-3 py-1.5 text-xs font-semibold transition-colors ' +
                            (isActive
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-900/40 dark:text-indigo-200'
                                : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600')
                        }
                        aria-pressed={isActive}
                    >
                        {preset.label}
                    </button>
                );
            })}

            <Popover className="relative">
                {({ open }) => (
                    <>
                        <Popover.Button
                            type="button"
                            data-testid="date-range-custom"
                            className={
                                'rounded-md border px-3 py-1.5 text-xs font-semibold transition-colors ' +
                                (isCustom
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:border-indigo-400 dark:bg-indigo-900/40 dark:text-indigo-200'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-gray-600')
                            }
                        >
                            {isCustom && from && to
                                ? `${from} → ${to}`
                                : 'Custom…'}
                        </Popover.Button>
                        <Transition
                            as={Fragment}
                            enter="transition ease-out duration-150"
                            enterFrom="opacity-0 translate-y-1"
                            enterTo="opacity-100 translate-y-0"
                            leave="transition ease-in duration-100"
                            leaveFrom="opacity-100 translate-y-0"
                            leaveTo="opacity-0 translate-y-1"
                        >
                            <Popover.Panel
                                className="absolute right-0 z-20 mt-2 w-72 rounded-lg border border-gray-200 bg-white p-4 shadow-card-hover dark:border-gray-700 dark:bg-gray-800"
                                data-testid="date-range-custom-panel"
                            >
                                <div className="space-y-3">
                                    <div>
                                        <label className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">From</label>
                                        <input
                                            type="date"
                                            value={from}
                                            onChange={(e) => setFrom(e.target.value)}
                                            data-testid="date-range-custom-from"
                                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        />
                                    </div>
                                    <div>
                                        <label className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">To</label>
                                        <input
                                            type="date"
                                            value={to}
                                            onChange={(e) => setTo(e.target.value)}
                                            data-testid="date-range-custom-to"
                                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                                        />
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <button
                                            type="button"
                                            onClick={() => { setFrom(''); setTo(''); }}
                                            className="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                        >
                                            Clear
                                        </button>
                                        <button
                                            type="button"
                                            disabled={!from || !to || from > to}
                                            onClick={() => pushRange(target, 'custom', from, to)}
                                            data-testid="date-range-custom-apply"
                                            className="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-gray-300 dark:disabled:bg-gray-600"
                                        >
                                            Apply
                                        </button>
                                    </div>
                                </div>
                            </Popover.Panel>
                        </Transition>
                    </>
                )}
            </Popover>
        </div>
    );
}
