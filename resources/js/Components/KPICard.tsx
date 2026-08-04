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
    /**
     * 2026-08-04 — optional click handler. When provided the card renders as
     * an interactive element (cursor-pointer + role=button + Enter/Space
     * keyboard activation) so a KPI can open a detail surface (e.g. the
     * leader's assigned-guests list). Backward compatible — absent by default.
     */
    onClick?: () => void;
    /**
     * 2026-08-04 — optional Lucide/Heroicons-style inline SVG content (24x24
     * stroke paths; the wrapper svg supplies viewBox + stroke props). Rendered
     * in a small accent-tinted chip beside the caption so each KPI reads at a
     * glance. Purely decorative (aria-hidden) — absent by default.
     */
    icon?: ReactNode;
    /**
     * 2026-08-04 — optional action helper text for clickable cards (e.g.
     * "View pending guests"). Rendered in the trend slot in an action color
     * so the card reads as a navigable surface. Only meaningful alongside
     * `onClick`; absent by default.
     */
    clickHint?: string;
    /**
     * 2026-08-04 — optional Escape-key handler for clickable cards. Called
     * when the card has keyboard focus and the user presses Escape (e.g. to
     * dismiss a surface the card opened). The modal it opens also closes on
     * Escape via HeadlessUI; this covers the card itself so the Escape
     * contract holds whether focus is on the card or inside the dialog.
     */
    onEscape?: () => void;
}>;

/**
 * Reusable KPI card — Phase 06b polish + Phase 13 visual refinement.
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
 *
 * Phase 13 Premium polish (Minimax prompt — "elegant KPI cards"):
 *  - Upgraded from float-on-hover (`hover:-translate-y-0.5`) to a softer
 *    hover ring + subtle shadow-depth grow. Float felt "marketing-banner";
 *    the ring feels like a premium ops dashboard (Linear / Vercel pattern).
 *  - 2px left accent stripe tied to the accent color for at-a-glance
 *    category signaling (operations dashboards rarely use color labels).
 *  - Sparkline sits flush with the bottom edge (no `mt-2`) and is
 *    clipped to the bottom of the card via overflow-hidden so it looks
 *    like a built-in miniature rather than a grafted SVG.
 *  - Trend slot moved next to delta in a "footer band" below the big
 *    number so captions → number → micro-context reads top-to-bottom.
 *  - `tabular-nums` on the big number so digits align when stacked.
 *
 * data-testid contracts preserved verbatim:
 *   - `data-testid=kpi-${caption-kebab}` (outer)
 *   - `data-testid=kpi-delta-${caption-kebab}` (delta chip)
 *   - `data-testid=kpi-sparkline-${caption-kebab}` (sparkline wrapper)
 *
 * Verifier (scripts/verify_phase06b_run.php) literal-class checks still
 * pass — `motion-safe:animate-fade-in`, `shadow-card`, and
 * `hover:shadow-card-hover` remain embedded in the outer div.
 */
const accentClasses: Record<NonNullable<Props['accent']>, string> = {
    default: 'text-gray-900 dark:text-gray-100',
    indigo:  'text-indigo-600 dark:text-indigo-400',
    emerald: 'text-emerald-600 dark:text-emerald-400',
    amber:   'text-amber-600 dark:text-amber-400',
    rose:    'text-rose-600 dark:text-rose-400',
    blue:    'text-blue-600 dark:text-blue-400',
};

const accentStripeClasses: Record<NonNullable<Props['accent']>, string> = {
    default: '',
    indigo:  'bg-indigo-500 dark:bg-indigo-400/90',
    emerald: 'bg-emerald-500 dark:bg-emerald-400/90',
    amber:   'bg-amber-500 dark:bg-amber-400/90',
    rose:    'bg-rose-500 dark:bg-rose-400/90',
    blue:    'bg-blue-500 dark:bg-blue-400/90',
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
    onClick,
    icon,
    clickHint,
    onEscape,
}: Props) {
    const positiveIsGood = delta?.positiveIsGood ?? true;
    const isPositive = (delta?.value ?? 0) >= 0;
    const tone = isPositive === positiveIsGood ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400';

    // 2026-08-04 — optional icon-chip tints, matched to each accent so the
    // chip reads as part of the card's color language (mirrors the
    // quick-submit tile accentBg pattern in Dashboard.tsx).
    const iconChipClasses: Record<NonNullable<Props['accent']>, string> = {
        default: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        indigo:  'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300',
        emerald: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300',
        amber:   'bg-amber-50 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300',
        rose:    'bg-rose-50 text-rose-600 dark:bg-rose-900/40 dark:text-rose-300',
        blue:    'bg-blue-50 text-blue-600 dark:bg-blue-900/40 dark:text-blue-300',
    };
    // Clickable = interactive (onClick) OR an action hint is supplied — a
    // clickHint with no onClick would otherwise render a broken indigo
    // affordance on a non-interactive card.
    const isClickable = Boolean(onClick || clickHint);
    // 2026-08-04 — clickable cards get a stronger affordance on hover: a
    // slight lift, indigo border + ring highlight, and the `group` class so
    // the arrow chip tints indigo too. Informational cards keep the neutral
    // gray hover (preserved from Phase 13).
    const hoverCls = isClickable
        ? 'hover:-translate-y-0.5 hover:border-indigo-300 hover:ring-indigo-200/70 dark:hover:border-indigo-500 dark:hover:ring-indigo-700/50'
        : 'hover:border-gray-300 hover:ring-gray-200/80 dark:hover:border-gray-600 dark:hover:ring-gray-700/60';

    return (
        <div
            data-card
            data-testid={`kpi-${caption.toLowerCase().replace(/\s+/g, '-')}`}
            onClick={onClick}
            role={onClick ? 'button' : undefined}
            tabIndex={onClick ? 0 : undefined}
            onKeyDown={isClickable ? (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onClick?.();
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    onEscape?.();
                }
            } : undefined}
            aria-label={isClickable ? (clickHint ? `${caption} — ${clickHint}` : caption) : undefined}
            className={`motion-safe:animate-fade-in relative overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card transition-all duration-200 hover:shadow-card-hover ${hoverCls} dark:border-gray-700 dark:bg-gray-800 ${className} ${
                isClickable
                    // 2026-08-04 — keyboard a11y: a FocusVisible ring only when
                    // navigating by keyboard (focus-visible), never on mouse
                    // click. `focus:outline-none` suppresses the UA outline so
                    // the custom ring is the single focus signal.
                    ? 'group cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800'
                    : ''
            }`}
        >
            {accent !== 'default' && (
                <span
                    aria-hidden="true"
                    className={`pointer-events-none absolute inset-y-0 left-0 w-[3px] ${accentStripeClasses[accent]}`}
                />
            )}
            <div className="px-5 py-4">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex min-w-0 items-center gap-2">
                        {icon && (
                            <span
                                aria-hidden="true"
                                className={`inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md ${iconChipClasses[accent]}`}
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5">
                                    {icon}
                                </svg>
                            </span>
                        )}
                        <div className="truncate text-[11px] font-medium uppercase tracking-[0.05em] text-gray-500 dark:text-gray-400">
                            {caption}
                        </div>
                    </div>
                    {isClickable ? (
                        /* 2026-08-04 — subtle click affordance: a small
                           arrow-up-right chip that tints indigo on card hover. */
                        <span
                            aria-hidden="true"
                            className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-50 text-gray-400 transition-colors duration-200 group-hover:bg-indigo-50 group-hover:text-indigo-600 dark:bg-gray-800/80 dark:text-gray-500 dark:group-hover:bg-indigo-900/40 dark:group-hover:text-indigo-300"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5">
                                <line x1="7" y1="17" x2="17" y2="7" />
                                <polyline points="7 7 17 7 17 17" />
                            </svg>
                        </span>
                    ) : delta ? (
                        <span
                            className={`inline-flex items-center gap-0.5 rounded-full bg-gray-50 px-1.5 py-0.5 text-[10.5px] font-semibold tabular-nums ${tone} dark:bg-gray-900/40`}
                            data-testid={`kpi-delta-${caption.toLowerCase().replace(/\s+/g, '-')}`}
                        >
                            <span aria-hidden="true">{isPositive ? '▲' : '▼'}</span>
                            {Math.abs(delta.value).toFixed(1)}%
                        </span>
                    ) : null}
                </div>
                <div className={`mt-1 text-[30px] font-semibold leading-none tabular-nums tracking-tight ${accentClasses[accent]}`}>
                    {animateValue && typeof value === 'number' ? (
                        <AnimatedCounter value={value} />
                    ) : (
                        value
                    )}
                </div>
                {clickHint ? (
                    <div className="mt-2 text-[11.5px] font-medium leading-snug text-indigo-600 dark:text-indigo-400">
                        {clickHint}
                    </div>
                ) : trend ? (
                    <div className="mt-2 text-[11.5px] leading-snug text-gray-500 dark:text-gray-400">
                        {trend}
                    </div>
                ) : null}
                {children && <div className="mt-3">{children}</div>}
            </div>
            {/* Phase 06d.0 — inline sparkline clipped flush to bottom edge so it reads as a built-in mini-chart. width=220 height=28 fills the typical KPI card width without measuring — viewBox scales the line, so the SVG preserves ratio on any card width. */}
            {series && series.length >= 2 && (
                <div
                    className="-mt-1 h-7 w-full"
                    data-testid={`kpi-sparkline-${caption.toLowerCase().replace(/\s+/g, '-')}`}
                >
                    <Sparkline series={series} tone={sparkTone ?? accent} width={220} height={28} />
                </div>
            )}
        </div>
    );
}
