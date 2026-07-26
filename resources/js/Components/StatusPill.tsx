import { ReactNode } from 'react';

type Tone = 'neutral' | 'success' | 'warn' | 'danger' | 'brand';

type Props = {
    tone?: Tone;
    children: ReactNode;
};

const toneClasses: Record<Tone, string> = {
    neutral:
        'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    success:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    warn:
        'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    danger:
        'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
    brand:
        'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

/**
 * Phase 05 — Status pill. Per Implementation/06 § Tables:
 *   `bg-bg-soft-2`, `text-text-secondary`, `rounded-full px-2 py-0.5
 *   text-[11px] font-medium`
 * Adapted for Tailwind v4 + dark mode.
 */
export default function StatusPill({ tone = 'neutral', children }: Props) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${toneClasses[tone]}`}
        >
            {children}
        </span>
    );
}
