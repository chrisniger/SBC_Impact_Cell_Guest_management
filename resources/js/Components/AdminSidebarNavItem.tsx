import { Link } from '@inertiajs/react';

type IconPath = React.ReactNode;

type Props = {
    label: string;
    href: string;
    active: boolean;
    iconPath: IconPath;
    collapsed: boolean;
    /**
     * Phase 06e — `inert` flag for items inside a muted role section.
     * - Renders as a non-clickable `<span>` (not a router-link) so a click
     *   does NOT navigate.
     * - Opacity is dropped to 60% and cursor goes to `not-allowed`.
     * - A "Coming soon" tooltip surfaces on hover.
     *
     * Used when the user is on Administrator and the sidebar renders
     * sibling role sections that aren't yet interactive. (Per user
     * request: "the other side menu are inactive".)
     */
    inert?: boolean;
};

export default function AdminSidebarNavItem({ label, href, active, iconPath, collapsed, inert = false }: Props) {
    // Phase 06f — `min-h-[44px]` raises the tap target to satisfy Apple
    // HIG / WCAG 2.5.5 on touch devices. The icon is vertically centered
    // inside the row so the extra height never breaks visual alignment.
    // On desktop (>= lg) we keep the previous denser spacing.
    const baseClasses = 'group relative flex min-h-[44px] items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-all duration-150 lg:min-h-0';
    const stateClasses = inert
        ? 'cursor-not-allowed opacity-60 text-gray-400 dark:text-gray-600'
        : active
        ? 'bg-brand-red-soft text-brand-red dark:bg-rose-900/20 dark:text-rose-300'
        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5';

    const testId = `sidebar-nav-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;

    const body = (
        <>
            {/* Active indicator: 3px red bar on the left edge */}
            {active && !inert && (
                <span
                    aria-hidden="true"
                    className="absolute inset-y-1 left-0 w-[3px] rounded-r-full bg-brand-red dark:bg-rose-400"
                />
            )}
            <span
                className={`flex h-5 w-5 shrink-0 items-center justify-center ${
                    inert
                        ? 'text-gray-300 dark:text-gray-700'
                        : active
                        ? 'text-brand-red dark:text-rose-300'
                        : 'text-gray-500 dark:text-gray-400'
                }`}
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="h-5 w-5"
                    aria-hidden="true"
                >
                    {iconPath}
                </svg>
            </span>
            {!collapsed && <span className="truncate">{label}</span>}
        </>
    );

    if (inert) {
        // Phase 06e accessibility fix — use a real <button disabled> in
        // place of <span role="link" aria-disabled>. role="link" on a
        // non-anchor was a screen-reader anti-pattern; <button disabled>
        // is semantically correct, focusable (but inert), and announces
        // the disabled state natively.
        return (
            <button
                type="button"
                disabled
                aria-disabled="true"
                title={collapsed ? label : 'Coming soon'}
                data-testid={testId}
                data-inert="true"
                className={`${baseClasses} ${stateClasses} text-left`}
            >
                {body}
            </button>
        );
    }

    return (
        <Link
            href={href}
            title={collapsed ? label : undefined}
            data-testid={testId}
            className={`${baseClasses} ${stateClasses}`}
        >
            {body}
        </Link>
    );
}
