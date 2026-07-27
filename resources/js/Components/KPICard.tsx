import { PropsWithChildren, ReactNode } from 'react';

type Props = PropsWithChildren<{
    /** Caption text, e.g. "PENDING CONTACTS" — already uppercased by caller */
    caption: string;
    /** Big number shown below the caption */
    value: string | number;
    /** Optional trend slot rendered beneath the number */
    trend?: ReactNode;
    /**
     * Optional delta indicator. Renders a small `▲ / ▼` + the change number
     * next to the caption. Default is null (no indicator).
     */
    delta?: { value: number; positiveIsGood?: boolean };
    /**
     * Accent color for the big number. Default is `default` (gray-900).
     */
    accent?: 'default' | 'indigo' | 'emerald' | 'amber' | 'rose' | 'blue';
    /** Optional className override for the outer card */
    className?: string;
}>;

/**
 * Reusable KPI card — Phase 06b polish.
 *
 * Per Implementation/06_Dashboard_Design_System.md + Phase 06b:
 *  - 12px radius, 1px border, soft shadow on light mode
 *  - On dark mode: 1px border at gray-700 + dark surface
 *  - Hover lift + motion-safe fade-in
 *  - Optional accent + delta (backward-compatible additions)
 */
const accentClasses: Record<NonNullable<Props['accent']>, string> = {
    default: 'text-gray-900 dark:text-gray-100',
    indigo:  'text-indigo-600 dark:text-indigo-400',
    emerald: 'text-emerald-600 dark:text-emerald-400',
    amber:   'text-amber-600 dark:text-amber-400',
    rose:    'text-rose-600 dark:text-rose-400',
    blue:    'text-blue-600 dark:text-blue-400',
};

export default function KPICard({
    caption,
    value,
    trend,
    delta,
    accent = 'default',
    className = '',
    children,
}: Props) {
    const positiveIsGood = delta?.positiveIsGood ?? true;
    const isPositive = (delta?.value ?? 0) >= 0;
    const tone = isPositive === positiveIsGood ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';

    return (
        <div
            data-card
            data-testid={`kpi-${caption.toLowerCase().replace(/\s+/g, '-')}`}
            className={`group rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-[0_4px_20px_rgba(0,0,0,0.03)] transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)] motion-safe:animate-[fadeIn_0.4s_ease-out] dark:border-gray-700 dark:bg-gray-800 ${className}`}
        >
            <div className="flex items-center justify-between">
                <div className="text-[11px] font-medium uppercase tracking-[0.05em] text-gray-500 dark:text-gray-400">
                    {caption}
                </div>
                {delta && (
                    <span
                        className={`inline-flex items-center gap-0.5 text-[11px] font-semibold ${tone}`}
                        data-testid={`kpi-delta-${caption.toLowerCase().replace(/\s+/g, '-')}`}
                    >
                        <span aria-hidden="true">{isPositive ? '▲' : '▼'}</span>
                        {Math.abs(delta.value).toFixed(1)}%
                    </span>
                )}
            </div>
            <div className={`mt-1 text-[32px] font-semibold leading-tight ${accentClasses[accent]}`}>
                {value}
            </div>
            {trend && (
                <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {trend}
                </div>
            )}
            {children && <div className="mt-3">{children}</div>}
        </div>
    );
}
