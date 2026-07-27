import { PropsWithChildren, ReactNode } from 'react';
import AnimatedCounter from '@/Components/AnimatedCounter';
import Sparkline from '@/Components/Sparkline';

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
    /**
     * Phase 06d.0 — optional sparkline series (daily values, oldest to newest).
     * When present and length >= 2, renders an inline SVG trend at the bottom.
     */
    series?: number[];
    /** Phase 06d.0 — sparkline accent color. Defaults to accent or 'indigo'. */
    sparkTone?: 'default' | 'indigo' | 'emerald' | 'amber' | 'rose' | 'blue';
    /** Phase 06d.0 — render the big number with requestAnimationFrame easeOutQuad */
    animateValue?: boolean;
}>;

/**
 * Reusable KPI card — Phase 06b polish.
 *
 * Per Implementation/06_Dashboard_Design_System.md + Phase 06b:
 *  - 12px radius, 1px border, soft shadow on light mode
 *  - On dark mode: 1px border at gray-700 + dark surface
 *  - Hover lift + motion-safe fade-in
 *  - Optional accent + delta (backward-compatible additions)
 *
 * Phase 06b: hardcoded shadow/animation utilities replaced with named
 *   tokens from tailwind.config.js: shadow-card, shadow-card-hover,
 *   animate-fade-in. See Implementation/Phase_06b-06c_UI_Polish.md §2.2.
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
    series,
    sparkTone,
    animateValue = false,
}: Props) {
    const positiveIsGood = delta?.positiveIsGood ?? true;
    const isPositive = (delta?.value ?? 0) >= 0;
    const tone = isPositive === positiveIsGood ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';

    return (
        <div
            data-card
            data-testid={`kpi-${caption.toLowerCase().replace(/\s+/g, '-')}`}
            className={`group rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-card transition-all duration-200 hover:-translate-y-0.5 hover:shadow-card-hover motion-safe:animate-fade-in dark:border-gray-700 dark:bg-gray-800 ${className}`}
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
                {animateValue && typeof value === 'number' ? (
                    <AnimatedCounter value={value} />
                ) : (
                    value
                )}
            </div>
            {trend && (
                <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {trend}
                </div>
            )}
            {/* Phase 06d.0 — inline sparkline (renders null when series has < 2 points) */}
            {series && series.length >= 2 && (
                <div className="mt-2 -mb-1" data-testid={`kpi-sparkline-${caption.toLowerCase().replace(/\s+/g, '-')}`}>
                    <Sparkline series={series} tone={sparkTone ?? accent} width={140} height={28} />
                </div>
            )}
            {children && <div className="mt-3">{children}</div>}
        </div>
    );
}


