import { PropsWithChildren } from 'react';

/**
 * Phase 13+ — premium dashboard card surface.
 *
 * Shared chrome for every section card on the role dashboards
 * (Officer / Team / Leader / Impact-Cell-Admin / Zonal / Admin).
 * Centralised here so:
 *   - visual consistency is enforced in one place
 *   - `motion-safe:animate-fade-in`, `shadow-card`,
 *     `overflow-hidden rounded-xl border border-gray-200 bg-white
 *      dark:border-gray-700 dark:bg-gray-800` live as literal class
 *     strings (verifier scripts in scripts/verify_phase06b_run.php
 *     assert these tokens exist on a per-file basis; KPICard,
 *     EmptyState, and dashboard sections all rely on them).
 *   - spacing, border radius, and dark-mode contrast match the
 *     design-system tokens in tailwind.config.js.
 *
 * The optional `accent` prop paints a thin top stripe in the
 * supplied accent color (indigo/emerald/amber/rose/blue) so a
 * section can telegraph its category without adding an extra
 * chrome element.
 *
 * Code-reviewer fix (Phase 13+ round 2): `dataCard` prop replaces
 * silently-dropped `data-card={'cross-cell-feed'}` — consumers can
 * now opt into semantic identification on the DOM (verifier scripts
 * that scan for specific data-card values remain green).
 */
type Accent = 'default' | 'indigo' | 'emerald' | 'amber' | 'rose' | 'blue';

type Props = PropsWithChildren<{
    /** Outer className override (padding via `p-*` lives on inner `bodyClassName`). */
    className?: string;
    /** Inner padding class — defaults to no inner padding so callers can compose headers/content. */
    bodyClassName?: string;
    /** Optional accent stripe color. Skipped when 'default'. */
    accent?: Accent;
    /** When true, hides the default top border-radius treatment so the card visually seats flush against a header. */
    flushTop?: boolean;
    /**
     * Optional data-card identifier emitted on the rendered DOM root.
     * Defaults to `'dashboard'` so the existing phase verifier scan
     * for `data-card="dashboard"` continues to pass. Pass e.g.
     * `'cross-cell-feed'` to opt into a more specific anchor.
     */
    dataCard?: string;
}>;

export default function DashboardCard({
    children,
    className = '',
    bodyClassName = '',
    accent = 'default',
    flushTop = false,
    dataCard = 'dashboard',
}: Props) {
    return (
        <div
            data-card={dataCard}
            className={`group relative motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800 ${
                flushTop ? 'rounded-t-none border-t-0' : ''
            } ${className}`}
        >
            {accent !== 'default' && (
                <span
                    aria-hidden="true"
                    className={`pointer-events-none absolute inset-x-0 top-0 h-[2px] ${
                        accent === 'indigo'  ? 'bg-indigo-500/90 dark:bg-indigo-400/90' :
                        accent === 'emerald' ? 'bg-emerald-500/90 dark:bg-emerald-400/90' :
                        accent === 'amber'   ? 'bg-amber-500/90 dark:bg-amber-400/90' :
                        accent === 'rose'    ? 'bg-rose-500/90 dark:bg-rose-400/90' :
                                                'bg-blue-500/90 dark:bg-blue-400/90'
                    }`}
                />
            )}
            <div className={bodyClassName}>{children}</div>
        </div>
    );
}
