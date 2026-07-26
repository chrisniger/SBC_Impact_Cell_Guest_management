import { PropsWithChildren, ReactNode } from 'react';

type Props = PropsWithChildren<{
    /** Caption text, e.g. "PENDING CONTACTS" — already uppercased by caller */
    caption: string;
    /** Big number shown below the caption */
    value: string | number;
    /** Optional trend slot rendered beneath the number */
    trend?: ReactNode;
}>;

/**
 * Reusable KPI card — Phase 05.
 *
 * Per Implementation/06_Dashboard_Design_System.md:
 *  - 12px radius, 1px border, soft shadow on light mode
 *  - On dark mode: inner 1px ring at 8% white for depth
 *  - No heavy gradients on the card itself
 */
export default function KPICard({ caption, value, trend, children }: Props) {
    return (
        <div
            data-card
            className="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800"
        >
            <div className="text-[11px] font-medium uppercase tracking-[0.05em] text-gray-500 dark:text-gray-400">
                {caption}
            </div>
            <div className="mt-1 text-[32px] font-semibold leading-tight text-gray-900 dark:text-gray-100">
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
