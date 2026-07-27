import { ReactNode } from 'react';

type Tone = 'neutral' | 'success' | 'warn' | 'danger' | 'brand' | 'info';
type Size = 'sm' | 'md';

type Props = {
    tone?: Tone;
    children: ReactNode;
    /** Show a leading colored dot */
    dot?: boolean;
    /** Size variant */
    size?: Size;
    /** Optional className override */
    className?: string;
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
    info:
        'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
};

const toneDotClasses: Record<Tone, string> = {
    neutral:  'bg-gray-500',
    success:  'bg-emerald-500',
    warn:     'bg-amber-500',
    danger:   'bg-rose-500',
    brand:    'bg-red-500',
    info:     'bg-blue-500',
};

const sizeClasses: Record<Size, string> = {
    sm: 'text-[10px] px-2 py-0.5',
    md: 'text-[11px] px-2.5 py-0.5',
};

/**
 * Phase 06b — Status pill. Per Implementation/06 § Tables:
 *   `bg-bg-soft-2`, `text-text-secondary`, `rounded-full px-2 py-0.5
 *   text-[11px] font-medium` + dot option + size variant + info tone.
 */
export default function StatusPill({
    tone = 'neutral',
    children,
    dot = false,
    size = 'md',
    className = '',
}: Props) {
    return (
        <span
            data-testid="pill-status"
            className={`inline-flex items-center gap-1.5 rounded-full font-medium ${sizeClasses[size]} ${toneClasses[tone]} ${className}`}
        >
            {dot && (
                <span
                    aria-hidden="true"
                    className={`inline-block h-1.5 w-1.5 rounded-full ${toneDotClasses[tone]}`}
                />
            )}
            {children}
        </span>
    );
}
