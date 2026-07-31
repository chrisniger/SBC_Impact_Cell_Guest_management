import { ReactNode } from 'react';

/**
 * Phase 13+ — premium section header pattern.
 *
 * Replaces the marketing-style `<h3>` + emoji icon header that was inlined
 * six times across Dashboard.tsx. Visual direction (per Minimax prompt —
 * "calm professional color system"):
 *   - small-caps `text-[11px] font-semibold tracking-wide text-gray-500`
 *     eyebrow (no emoji icon by default — pure typographic hierarchy)
 *   - single h2 weight `text-base font-semibold tracking-tight` title
 *   - optional one-line `text-sm text-gray-500` description
 *   - right-aligned action slot
 *
 * Existing inline `NoticeSection` consumers (Phase 06's `motion-safe:animate-fade-in`
 * strings) are preserved by wrapping the header in `<section>` so the
 * `verify_phase06b_run.php` token scans still pass.
 */
type Props = {
    /** Eyebrow text (renders as `text-[11px] uppercase tracking-wide text-gray-500`). */
    eyebrow?: ReactNode;
    /** Section title — required for the visual rhythm even when short. */
    title: ReactNode;
    /** Optional one-line description below the title. */
    description?: ReactNode;
    /** Optional count chip (renders as small pill). */
    count?: number | string;
    /** Optional right-aligned action (link, button, etc). */
    action?: ReactNode;
    /** Optional className override for the outer <section>. */
    className?: string;
    /** Optional inline icon slot to the LEFT of the title (small two-tone icon). */
    icon?: ReactNode;
    /** Override the data-testid of the outer <section>. */
    sectionTestId?: string;
    /** Section body — KPI grid, table, or any content. */
    children?: ReactNode;
};

export default function DashboardSection({
    eyebrow,
    title,
    description,
    count,
    action,
    className = '',
    icon,
    sectionTestId,
    children,
}: Props) {
    return (
        <section
            className={`motion-safe:animate-fade-in space-y-3 ${className}`}
            data-testid={sectionTestId}
        >
            <div className="flex items-start justify-between gap-4">
                <div className="min-w-0 flex items-start gap-3">
                    {icon && (
                        <span
                            aria-hidden="true"
                            className="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.6"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="h-4 w-4"
                            >
                                {icon}
                            </svg>
                        </span>
                    )}
                    <div className="min-w-0">
                        {eyebrow && (
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {eyebrow}
                            </p>
                        )}
                        <div className="mt-0.5 flex items-center gap-2">
                            <h3 className="text-base font-semibold tracking-tight text-gray-900 dark:text-white">
                                {title}
                            </h3>
                            {count !== undefined && (
                                <span
                                    aria-label={`${count} items`}
                                    className="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {count}
                                </span>
                            )}
                        </div>
                        {description && (
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {description}
                            </p>
                        )}
                    </div>
                </div>
                {action && <div className="shrink-0">{action}</div>}
            </div>
            {/* Code-reviewer fix (Phase 13+ round 2): solid 1px hairline for guaranteed contrast in Safari/Firefox where `via-40%` stops sometimes render near-invisible. */}
            <div aria-hidden="true" className="h-px w-full bg-gray-200/80 dark:bg-gray-700/60" />
            {children && <div>{children}</div>}
        </section>
    );
}
