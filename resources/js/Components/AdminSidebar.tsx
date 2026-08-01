import AdminSidebarNavItem from '@/Components/AdminSidebarNavItem';

/**
 * Phase 06d.0 + 06e + 06f — admin sidebar, role-aware, mobile-aware.
 *
 * Renders 5 role-grouped sections:
 *   1. Administrator        (12 nav items)
 *   2. Impact Cell Leader   (8 nav items)
 *   3. Follow-Up Officer    (3 nav items)
 *   4. Follow-Up Team       (3 nav items)
 *   5. Zonal Coordinator    (5 nav items)
 *
 * Visibility rules:
 *   - `activeRole === 'Administrator'`  → render ALL 5 sections.
 *     Section 1 (Administrator) renders with full active styling on its matched route.
 *     Sections 2–5 render with each item `inert=true` (non-clickable, opacity-60,
 *     "Coming soon" tooltip). This implements the user's spec:
 *     "user on the administrator and other side menu are inactive".
 *
 *   - Other active roles          → render ONLY the matching section, full strength.
 *     Administrator remains accessible (for emergency admin actions) but its items
 *     are also rendered inert so the user isn't confused about scope.
 *
 * Phase 06f — Mobile responsiveness:
 *   - The aside parent (`AdminDashboardLayout`) positions this component as a
 *     fixed off-canvas drawer on `< lg` and as an aside in flex flow on `>= lg`.
 *   - This component no longer manages its own outer width — the inner wrapper
 *     fills the parent (`w-full`) and the parent's responsive width class
 *     controls the visible footprint.
 *   - `onClose` prop is wired to the X icon in the logo block; clicking it
 *     delegates back up to the layout so all four close paths (X, backdrop,
 *     Escape, route navigation) end in the same `setState` call.
 *   - The bottom "Collapse" footer is suppressed on `< lg` because a 72px
 *     collapsed drawer inside a 280px drawer-aside is an anti-pattern.
 */

type NavItem = {
    label: string;
    href: string;
    routeName: string;
    iconPath: React.ReactNode;
};

type Section = {
    key: string;
    label: string;
    items: NavItem[];
};

// ─────────────────────────────────────────────────────────────────────
// Icon glyph library — kept inline so each <svg> renders identically to
// the master NAV_ITEMS list in the old December build (no extra fetching).
// ─────────────────────────────────────────────────────────────────────
const ICON_DASHBOARD   = <><rect x="3" y="3" width="7" height="9" /><rect x="14" y="3" width="7" height="5" /><rect x="14" y="12" width="7" height="9" /><rect x="3" y="16" width="7" height="5" /></>;
const ICON_USERS       = <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></>;
const ICON_CELLS       = <><rect x="3" y="3" width="18" height="18" rx="2" /><path d="M3 9h18M9 21V9" /></>;
const ICON_SUBMIT      = <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /></>;
const ICON_REPORTS     = <><path d="M3 3v18h18" /><path d="M7 14l3-3 4 4 7-7" /></>;
const ICON_ANALYTICS   = <><circle cx="11" cy="11" r="7" /><line x1="20" y1="20" x2="16.65" y2="16.65" /></>;
const ICON_CSV         = <><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="17 8 12 3 7 8" /><line x1="12" y1="3" x2="12" y2="15" /></>;
const ICON_BELL        = <><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></>;
const ICON_MSG         = <><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" /></>;
const ICON_USERS_ADMIN = <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>;
const ICON_ROLES       = <><path d="M12 2l3 6 6 1-4.5 4 1 6L12 16l-5.5 3 1-6L3 9l6-1z" /></>;
const ICON_AUDIT       = <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="9" y1="13" x2="15" y2="13" /></>;
const ICON_SETTINGS    = <><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></>;
const ICON_PEOPLE      = <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>;
const ICON_PLUS_DOC    = <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="9" y1="13" x2="15" y2="13" /></>;
const ICON_HEART       = <><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" /></>;
const ICON_LEADER      = <><polygon points="12 2 15 8.5 22 9.3 17 14.2 18.5 21 12 17.8 5.5 21 7 14.2 2 9.3 9 8.5 12 2" /></>;
const ICON_DOWNLOAD    = <><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></>;

// ─────────────────────────────────────────────────────────────────────
// Section definitions — single source of truth for sidebar contents
// ─────────────────────────────────────────────────────────────────────
const SECTIONS: Section[] = [
    {
        key: 'admin',
        label: 'Administrator',
        items: [
            { label: 'Dashboard',           href: route('dashboard'),                  routeName: 'dashboard',                  iconPath: ICON_DASHBOARD },
            { label: 'Guests',              href: route('guests.index'),               routeName: 'guests.index',               iconPath: ICON_USERS },
            { label: 'Impact Cells',        href: route('impact-cells.index'),         routeName: 'impact-cells.index',         iconPath: ICON_CELLS },
            { label: 'Submissions',         href: route('admin.submissions.index'),    routeName: 'admin.submissions.index',    iconPath: ICON_SUBMIT },
            { label: 'Reports',             href: route('reports.index'),              routeName: 'reports.index',              iconPath: ICON_REPORTS },
            { label: 'Analytics',           href: route('admin.analytics.index'),      routeName: 'admin.analytics.index',      iconPath: ICON_ANALYTICS },
            { label: 'CSV Import',          href: route('csv.import'),                 routeName: 'csv.import',                 iconPath: ICON_CSV },
            { label: 'Notifications',       href: route('notification-settings.index'),routeName: 'notification-settings.index',iconPath: ICON_BELL },
            { label: 'Messages',            href: route('admin.messages.index'),       routeName: 'admin.messages.index',       iconPath: ICON_MSG },
            { label: 'Users',               href: route('admin.users.index'),          routeName: 'admin.users.index',          iconPath: ICON_USERS_ADMIN },
            { label: 'Roles & Permissions', href: route('admin.roles-permissions.index'), routeName: 'admin.roles-permissions.index', iconPath: ICON_ROLES },
            { label: 'Audit Log',           href: route('audit.index'),                routeName: 'audit.index',                iconPath: ICON_AUDIT },
            { label: 'Settings',            href: route('profile.edit'),               routeName: 'profile.edit',               iconPath: ICON_SETTINGS },
        ],
    },
    {
        key: 'impactCell',
        label: 'Impact Cell Leader',
        items: [
            { label: 'Dashboard',           href: route('dashboard'),                          routeName: 'dashboard',                          iconPath: ICON_DASHBOARD },
            { label: 'Members Data',        href: '/impact-submissions/create?type=member',    routeName: 'impact-submissions.create',          iconPath: ICON_PEOPLE },
            { label: 'Submit Report',       href: '/impact-submissions/create?type=report',    routeName: 'impact-submissions.create',          iconPath: ICON_SUBMIT },
            { label: 'Childbirth Notice',   href: '/impact-submissions/create?type=childbirth', routeName: 'impact-submissions.create',         iconPath: ICON_HEART },
            { label: 'Souls Registration',  href: '/impact-submissions/create?type=soul',      routeName: 'impact-submissions.create',          iconPath: ICON_PLUS_DOC },
            { label: 'Soul Search',         href: route('impact-submissions.soul-search'),     routeName: 'impact-submissions.soul-search',     iconPath: ICON_LEADER },
            { label: 'My Reports',          href: route('impact-submissions.my-reports'),      routeName: 'impact-submissions.my-reports',      iconPath: ICON_REPORTS },
            { label: 'Leadership Board',    href: route('leadership.index'),                   routeName: 'leadership.index',                   iconPath: ICON_CELLS },
        ],
    },
    {
        key: 'followUpOfficer',
        label: 'Follow-Up Officer',
        items: [
            { label: 'Dashboard',  href: route('dashboard'),  routeName: 'dashboard',  iconPath: ICON_DASHBOARD },
            { label: 'My Guests',  href: route('guests.index'), routeName: 'guests.index', iconPath: ICON_USERS },
            { label: 'Export CSV', href: route('csv.export'), routeName: 'csv.export', iconPath: ICON_DOWNLOAD },
        ],
    },
    {
        key: 'followUpTeam',
        label: 'Follow-Up Team',
        items: [
            { label: 'Dashboard',  href: route('dashboard'),  routeName: 'dashboard',  iconPath: ICON_DASHBOARD },
            { label: 'My Guests',  href: route('guests.index'), routeName: 'guests.index', iconPath: ICON_USERS },
            { label: 'Export CSV', href: route('csv.export'), routeName: 'csv.export', iconPath: ICON_DOWNLOAD },
        ],
    },
    {
        key: 'impactZonal',
        label: 'Zonal Coordinator',
        items: [
            { label: 'Dashboard',    href: route('dashboard'),          routeName: 'dashboard',          iconPath: ICON_DASHBOARD },
            { label: 'Impact Cells', href: route('impact-cells.index'), routeName: 'impact-cells.index', iconPath: ICON_CELLS },
            { label: 'Guests',       href: route('guests.index'),       routeName: 'guests.index',       iconPath: ICON_USERS },
            { label: 'Reports',      href: route('reports.index'),      routeName: 'reports.index',      iconPath: ICON_REPORTS },
            { label: 'Export CSV',   href: route('csv.export'),         routeName: 'csv.export',         iconPath: ICON_DOWNLOAD },
        ],
    },
    // Phase 09 — Impact_Cell_Admin (cross-cell + cross-zonal supervisor).
    {
        key: 'impactCellAdmin',
        label: 'Impact Cell Administrator',
        items: [
            { label: 'Dashboard',         href: route('dashboard'),                     routeName: 'dashboard',                     iconPath: ICON_DASHBOARD },
            { label: 'Impact Cells',      href: route('impact-cells.index'),            routeName: 'impact-cells.index',            iconPath: ICON_CELLS },
            { label: 'Submissions',       href: route('impact-submissions.index'),      routeName: 'impact-submissions.index',      iconPath: ICON_SUBMIT },
            { label: 'My Reports',        href: route('impact-submissions.my-reports'), routeName: 'impact-submissions.my-reports', iconPath: ICON_REPORTS },
            { label: 'Leadership Board',  href: route('leadership.index'),              routeName: 'leadership.index',              iconPath: ICON_CELLS },
            { label: 'Guests',            href: route('guests.index'),                  routeName: 'guests.index',                  iconPath: ICON_USERS },
        ],
    },
];

function resolveOwnerSection(activeRole: string | null, activeGroup: string | null): string {
    if (activeRole === 'Administrator') return 'admin';
    // Phase 09 — Impact_Cell_Admin (regional supervisor with cross-cell + cross-zonal
    // visibility) must be checked BEFORE the impactCell group fallback because
    // groupOf('Impact_Cell_Admin') === 'impactCell' — the supervisor is technically
    // a member of the impactCell bucket but with widened scope.
    if (activeRole === 'Impact_Cell_Admin') return 'impactCellAdmin';
    if (activeRole === 'Impact_Zonal_Coordinator') return 'impactZonal';
    if (activeGroup === 'impactCell')   return 'impactCell';
    if (activeGroup === 'followUpOfficer') return 'followUpOfficer';
    if (activeGroup === 'followUpTeam') return 'followUpTeam';
    // Phase 06e fix — Supervisor falls back to 'admin' section but every
    // item is rendered inert. This preserves the previous AuthenticatedLayout
    // behavior for Supervisor (read-only access to Dashboard / Guests / Reports)
    // without granting them clicking access to the full 13-item admin sidebar.
    return 'admin';
}

type Props = {
    collapsed: boolean;
    onToggleCollapse: () => void;
    /**
     * Phase 06f — Mobile-only close affordance. Bound by the parent
     * (`AdminDashboardLayout`) to `setMobileOpen(false)` so the same
     * `useState` setter that drives the X-icon, the backdrop, and the
     * Escape / navigate handlers also drives this button. No-op on `>= lg`
     * (the X button itself is `lg:hidden`).
     */
    onClose: () => void;
    activeRole: string | null;
    activeGroup: string | null;
    /**
     * Phase 06e+2 — route names whose sidebar entries should be hidden
     * in the current environment. Sourced from
     * `usePage().props.gatedNavRoutes` (set by HandleInertiaRequests
     * via `GateStubPagesByEnvironment::hiddenNavRouteNames()`).
     *
     * In `${REVEAL_ENVS}` the backend returns `[]` so every entry is
     * rendered (verifier/engineer UX). In production (or any other
     * env) the backend returns the gated route names — items whose
     * `routeName` is in this list are filtered out of the rendered
     * output, so admins never see a sidebar link that would 404.
     */
    hiddenRouteNames?: string[];
};

export default function AdminSidebar({
    collapsed,
    onToggleCollapse,
    onClose,
    activeRole,
    activeGroup,
    hiddenRouteNames = [],
}: Props) {
    // Phase 06f — outer width is owned by the parent `<aside>`; this
    // inner wrapper just fills it. `lg:w-[*]` re-applies the desktop
    // collapsed/expanded width so the inner transitions still animate
    // smoothly when `collapsed` toggles on desktop.
    const widthClass = collapsed ? 'w-full lg:w-[72px]' : 'w-full lg:w-[260px]';

    // Real `route().current()` via Ziggy (synchronous window helper).
    // Matches each `item.routeName` against the current route name to drive
    // the active-state highlight. Wrapped in try/catch because `route()`
    // throws if Ziggy isn't hydrated yet (first paint race).
    let resolvedCurrentRoute = '';
    if (typeof window !== 'undefined' && (window as any).route) {
        try {
            resolvedCurrentRoute = (window as any).route().current() ?? '';
        } catch (_) {
            resolvedCurrentRoute = '';
        }
    }

    const ownerSectionKey = resolveOwnerSection(activeRole, activeGroup);

    // Phase 06e — three visibility tiers:
    //
    //   1. Administrator: every section; the admin section is fully live
    //      and the four non-admin sections render inert ("Coming soon").
    //   2. Non-Administrator with a known group/role: ONLY the matching
    //      section renders, fully live.
    //   3. Supervisor (or any unknown role): ONLY the admin section
    //      renders, but every item is inert. This replaces the previous
    //      AuthenticatedLayout behavior, which gave Supervisor 3 live
    //      items (Dashboard / Guests / Reports); we keep the same READ
    //      scope without exposing the click target. (Phase 06e+ can
    //      later whitelist Supervisor-readable items if needed.)
    let visibleSections: Section[];
    if (activeRole === 'Administrator') {
        visibleSections = SECTIONS;
    } else if (ownerSectionKey === 'admin' && activeRole === 'Supervisor') {
        // Supervisor → admin section visible but all items inert.
        visibleSections = SECTIONS.filter((s) => s.key === 'admin');
    } else {
        visibleSections = SECTIONS.filter((s) => s.key === ownerSectionKey);
    }

    // Phase 06e+2 — strip items whose `routeName` is in `hiddenRouteNames`.
    // Backend owns the gate (GateStubPagesByEnvironment) and ships the
    // hidden list through Inertia shared props; this filter is the
    // last-mile `useState`-free render-time application.
    visibleSections = visibleSections
        .map((section) => ({
            ...section,
            items: section.items.filter(
                (item) => !hiddenRouteNames.includes(item.routeName),
            ),
        }))
        // Drop whole sections that ended up with zero items (e.g. the
        // entire Administrator block in a heavily-gated production
        // config). Otherwise the section heading renders above nothing,
        // which looks like a bug.
        .filter((section) => section.items.length > 0);

    return (
        <div
            data-testid="admin-sidebar-inner"
            className={`${widthClass} flex h-full flex-col transition-[width] duration-250`}
        >
            {/* Logo block — brand-red square + SBC mark.
                Phase 06f — On `< lg`, an X close button is rendered to
                the right of the brand text so the user has a visible
                way to dismiss the drawer without resorting to the
                backdrop. Hidden on `>= lg` (the desktop sidebar stays
                open permanently). */}
            <div className="flex h-[60px] items-center gap-3 border-b border-gray-200/60 px-4 dark:border-gray-800/60">
                <span
                    data-testid="admin-sidebar-logo-mark"
                    className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-red text-white shadow-sm"
                >
                    <span className="text-sm font-bold tracking-tight">SBC</span>
                </span>
                {!collapsed && (
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-gray-900 dark:text-white">Summit Bible</p>
                        <p className="truncate text-[11px] uppercase tracking-[0.10em] text-gray-500 dark:text-gray-400">Impact Portal</p>
                    </div>
                )}

                {/* Phase 06f — mobile-only close button. */}
                {onClose && (
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close sidebar"
                        title="Close sidebar"
                        data-testid="admin-sidebar-close"
                        className="touch-manipulation ml-auto rounded-md p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-red/40 lg:hidden dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className="h-5 w-5"
                            aria-hidden="true"
                        >
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                )}
            </div>

            {/* Sections — one nav block per role-group */}
            <nav
                className="flex-1 space-y-4 overflow-y-auto px-2 py-4"
                data-testid={`admin-sidebar-nav-${ownerSectionKey}`}
            >
                {visibleSections.map((section, sectionIdx) => {
                    const isOwnerSection = section.key === ownerSectionKey;
                    return (
                        <div
                            key={section.key}
                            data-testid={`admin-sidebar-section-${section.key}`}
                            className="space-y-1"
                        >
                            {/* Section heading — visible only when sidebar expanded.
                                Non-owner sections get a muted label so the user
                                can read which group this would normally belong to. */}
                            {!collapsed && (
                                <div
                                    className={`flex items-center justify-between px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.10em] ${
                                        isOwnerSection
                                            ? 'text-gray-500 dark:text-gray-400'
                                            : 'text-gray-300 dark:text-gray-600'
                                    }`}
                                >
                                    <span>{section.label}</span>
                                    {!isOwnerSection && (
                                        <span
                                            className="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium normal-case text-gray-400 dark:bg-gray-800 dark:text-gray-600"
                                            title="Section is unavailable for your active role"
                                            data-testid={`admin-sidebar-section-${section.key}-inactive-pill`}
                                        >
                                            inactive
                                        </span>
                                    )}
                                </div>
                            )}
                            {section.items.map((item) => {
                                // Supervisor gets the admin section but every item is inert.
                                const itemInert = !isOwnerSection || (activeRole === 'Supervisor' && section.key === 'admin');
                                return (
                                    <AdminSidebarNavItem
                                        key={item.label}
                                        label={item.label}
                                        href={item.href}
                                        active={!itemInert && resolvedCurrentRoute === item.routeName}
                                        iconPath={item.iconPath}
                                        collapsed={collapsed}
                                        inert={itemInert}
                                    />
                                );
                            })}
                        </div>
                    );
                })}
            </nav>

            {/* Collapse toggle at footer.
                Phase 06f — wrapped in `hidden lg:block` because the
                desktop-only 72px collapsed state has no analogue on
                mobile (the drawer is fixed-width). On `< lg` this
                entire block is removed from the layout. */}
            <div className="hidden border-t border-gray-200/60 px-3 py-3 dark:border-gray-800/60 lg:block">
                <button
                    type="button"
                    onClick={onToggleCollapse}
                    className="touch-manipulation group flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                    data-testid="admin-sidebar-collapse-toggle"
                    title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                >
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.6"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className={`h-4 w-4 transition-transform duration-250 ${collapsed ? 'rotate-180' : ''}`}
                        aria-hidden="true"
                    >
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                    {!collapsed && <span>Collapse</span>}
                </button>
            </div>
        </div>
    );
}
