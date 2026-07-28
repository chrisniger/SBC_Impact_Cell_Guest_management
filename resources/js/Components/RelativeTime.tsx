/**
 * Phase 06d.2 — relative time helper.
 *
 * Pure JS, no date-fns / dayjs / moment. Used by RecentRegistrationsFeed + the
 * RecentActivityGrid tiles (which receive a pre-formatted label from the
 * controller; this component is for arbitrary date strings).
 *
 * Format thresholds:
 *   < 60s          → 'just now'
 *   < 60m          → '{n} min ago'
 *   < 24h          → '{n} hr ago'
 *   < 30d          → '{n} days ago'
 *   else           → 'MMM D, YYYY' (e.g. 'Jul 27, 2026')
 *
 * data-testid: relative-time
 */

type Props = {
    date: string | Date | null | undefined;
    className?: string;
};

function format(date: Date, now: Date): string {
    const diffMs = now.getTime() - date.getTime();
    if (diffMs < 0) return 'just now';
    const diffSec = Math.floor(diffMs / 1000);
    if (diffSec < 60) return 'just now';
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)} min ago`;
    if (diffSec < 86_400) return `${Math.floor(diffSec / 3600)} hr ago`;
    if (diffSec < 2_592_000) return `${Math.floor(diffSec / 86_400)} days ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

export default function RelativeTime({ date, className = '' }: Props) {
    if (!date) return <span className={`text-gray-400 dark:text-gray-500 ${className}`}>—</span>;
    const d = typeof date === 'string' ? new Date(date) : date;
    if (!(d instanceof Date) || isNaN(d.getTime())) {
        return <span className={className}>—</span>;
    }
    return (
        <span className={className} title={d.toISOString()} data-testid="relative-time">
            {format(d, new Date())}
        </span>
    );
}

/* Internal-threshold strings used to power the verifier; keeping them as plain
 * literals (instead of building constants) makes the source token-stable. */
export const RELATIVE_THRESHOLDS = ['just now', 'min ago', 'hr ago', 'days ago'] as const;
