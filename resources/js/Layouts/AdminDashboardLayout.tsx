import { PropsWithChildren, ReactNode, useCallback, useEffect, useRef, useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AdminSidebar from '@/Components/AdminSidebar';
import ApplicationLogo from '@/Components/ApplicationLogo';
import GlobalSearch, { SearchResult } from '@/Components/GlobalSearch';
import RoleBadge from '@/Components/RoleBadge';
import ThemeToggle from '@/Components/ThemeToggle';

type AdminAuthProps = Record<string, any> & {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            activeRole: string | null;
            activeGroup: string | null;
            roles: string[];
            hasMultipleRoles: boolean;
        };
    };
};

/**
 * Phase 06d.0 + 06e + 06f + 06g — unified dashboard layout,
 * mobile-responsive, swipe-aware, focus-trapped.
 *
 * Was admin-only (Dashboard variant === 'admin'). Phase 06e promotes it
 * to the universal layout for every role, with `AdminSidebar` rendering
 * 5 role-grouped sections and showing all five (with the others muted)
 * for Administrator, or the user's own group only for non-admin.
 *
 * Phase 06f — Mobile responsiveness:
 *   - `< lg` (< 1024px): sidebar is an off-canvas drawer driven by
 *     `mobileOpen` state. Hamburger button in the topbar toggles it;
 *     dark backdrop + Escape key + click-outside + body scroll lock
 *     keep focus on the drawer. Auto-closes on route navigation and
 *     whenever the viewport widens past `lg`.
 *   - `>= lg`: sidebar is a static flex-column that participates in
 *     layout. `collapsed` state shrinks it to 72px (Phase 06d.0).
 *     `mobileOpen` is a no-op here.
 *
 * Phase 06g — A11y polish:
 *   - Swipe-to-close: a leftward Pointer-Events drag (touch + pen) on
 *     the drawer body follows the finger with live translation; on
 *     release, the drawer snaps closed if the gesture crossed the
 *     distance OR velocity threshold. Mouse draws and clicks are
 *     untouched (the drawer's nav links still navigate normally).
 *   - Focus-trap: while drawer is open, Tab / Shift+Tab cycles inside
 *     the drawer's focusables instead of leaking into the inert main
 *     column. Sibling background gets the `inert` attribute (React 19
 *     native) so screen readers can't reach it. Initial focus lands on
 *     the first non-close focusable (per WAI-ARIA APG), and closing
 *     restores focus to the burger toggle the user just clicked.
 *
 * Topbar chrome is the same regardless of role or viewport:
 *   - hamburger toggle        (Phase 06f, `< lg` only)
 *   - global search input     (HeadlessUI Combobox, `>= lg` only)
 *   - theme toggle            (Phase 06e)
 *   - role badge
 *   - log-out button
 *
 * Sidebar collapse state persists in localStorage `cgms.adminSidebar.collapsed`.
 * Mobile drawer state is intentionally NOT persisted — the page should
 * always reopen on top of fresh content.
 */
export default function AdminDashboardLayout({
    header,
    children,
    footer,
    searchIndex,
}: PropsWithChildren<{ header?: ReactNode; footer?: ReactNode; searchIndex?: SearchResult[] }>) {
    const { auth, gatedNavRoutes } = usePage<AdminAuthProps & { gatedNavRoutes?: string[] }>().props;
    const user = auth.user!;

    const [collapsed, setCollapsed] = useState<boolean>(false);

    // Phase 06f — mobile drawer state. Independent from `collapsed`
    // (which is a desktop-only shrink toggle). Auto-clears when the
    // viewport widens past `lg` so the drawer doesn't linger as a stuck
    // closed-state on a freshly-resized desktop window.
    const [mobileOpen, setMobileOpen] = useState<boolean>(false);

    useEffect(() => {
        try {
            const stored = window.localStorage.getItem('cgms.adminSidebar.collapsed');
            if (stored === 'true') setCollapsed(true);
        } catch (_) {
            // localStorage can throw in private modes; defaults to expanded
        }
    }, []);

    const toggleCollapse = () => {
        setCollapsed((prev) => {
            const next = !prev;
            try {
                window.localStorage.setItem('cgms.adminSidebar.collapsed', String(next));
            } catch (_) { /* ignore */ }
            return next;
        });
    };

    const sidebarWidth = collapsed ? 'w-[72px]' : 'w-[260px]';

    // -----------------------------------------------------------------
    // Phase 06f — mobile drawer side-effects.
    //
    // Each effect is intentionally narrow and idempotent (safe to run on
    // every render) — that keeps the navigation handler itself trivial
    // and avoids stale-closure bugs when route params change.
    // -----------------------------------------------------------------

    // (a) Body scroll lock while drawer is open: prevents the underlying
    //     page from scrolling under the drawer on touch devices.
    useEffect(() => {
        if (!mobileOpen) return;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [mobileOpen]);

    // (b) Escape key closes the drawer when it has focus.
    useEffect(() => {
        if (!mobileOpen) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setMobileOpen(false);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [mobileOpen]);

    // (c) Auto-close when the viewport widens past `lg` (1024px). The
    //     desktop layout doesn't track `mobileOpen` so we MUST clear
    //     it here or the hamburger toggle would be one render behind.
    useEffect(() => {
        if (typeof window === 'undefined') return;
        const media = window.matchMedia('(min-width: 1024px)');
        const onMatch = (e: MediaQueryListEvent) => {
            if (e.matches) setMobileOpen(false);
        };
        media.addEventListener('change', onMatch);
        return () => media.removeEventListener('change', onMatch);
    }, []);

    // (d) Inertia navigation: close the drawer on every successful
    //     route change so the page reveal isn't covered by the drawer.
    useEffect(() => {
        return router.on('navigate', () => {
            setMobileOpen(false);
        });
    }, []);

    // -----------------------------------------------------------------
    // Phase 06g — swipe-to-close + focus-trap bookkeeping.
    //
    // The state lives here (the layout owns the drawer-aside), but the
    // behaviour is delegated to refs / effects so JSX stays declarative.
    // -----------------------------------------------------------------

    // Refs — refs to critical DOM nodes so the imperative pointer and
    // focus APIs can reach them. Refs never trigger renders but coexist
    // with state because we only READ from refs (no React-dependent
    // derivations).
    const drawerRef = useRef<HTMLElement | null>(null);
    const burgerRef = useRef<HTMLButtonElement | null>(null);
    const mainColumnRef = useRef<HTMLDivElement | null>(null);
    // Drag session record — survives renders, cleared between sessions.
    const dragSessionRef = useRef<{ startX: number; startT: number; pointerId: number } | null>(null);
    const dragRafRef = useRef<number | null>(null);

    // State — dragOffset drives the inline `translateX(...)` while the
    // user is dragging. isDragging suppresses the CSS transition so the
    // transform follows the finger with zero spring-back.
    const [dragOffset, setDragOffset] = useState<number>(0);
    // Phase 06g pol — State-mirrored so the FIRST render after pointerdown
    // (before any `setDragOffset` re-render fires) still gets correct
    // `isDragging ? '' : 'transition-transform...'` className. Using a
    // ref-derived boolean in expression position would silently lag by
    // one render; this state side steps that.
    const [isDragging, setIsDragging] = useState<boolean>(false);

    // Phase 06g — focus trap.
    //
    // We rely on three mechanisms ON TOP OF the existing `inert` on the
    // main column (applied via the `inert={!mobileOpen}` prop on the
    // outer main wrapper below):
    //
    //   1. Initial-focus on open: 60ms after `mobileOpen` becomes true
    //      (slide-in animation start), focus the first non-close
    //      focusable inside the drawer. Skip the X close button so
    //      keyboard users immediately hear the menu title / first item.
    //   2. Tab cycling: a window keydown listener watches for Tab /
    //      Shift+Tab and wraps focus around the drawer's focusables
    //      when the next focusable would otherwise land in the inert
    //      main column.
    //   3. Focus restoration on close: when `mobileOpen` flips to false,
    //      focus returns to the hamburger button the user originally
    //      pressed (preserves the muscle-memory flow).
    //
    // Combined, this implements the WAI-ARIA APG dialog-with-slide
    // pattern with React 19's native `inert` prop (no focus-trap dep).
    useEffect(() => {
        if (!mobileOpen) {
            // Restore focus to the burger toggle the user originally
            // clicked. Skip during SSR (no document) and only fire when
            // the drawer actually had focus previously.
            if (typeof document === 'undefined') return;
            if (burgerRef.current && document.activeElement && drawerRef.current?.contains(document.activeElement)) {
                burgerRef.current.focus();
            }
            return;
        }

        if (typeof window === 'undefined') return;

        // (1) initial focus — RAF-deferred so the slide-in animation
        //     has time to start before focus jumps.
        const initialFocusRaf = window.requestAnimationFrame(() => {
            if (!drawerRef.current) return;
            const focusables = drawerRef.current.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            for (const el of Array.from(focusables)) {
                // Skip the close button — focus moves to content first
                // per WAI-ARIA APG (the X is still reachable via Shift+Tab).
                if (el.getAttribute('data-testid') === 'admin-sidebar-close') continue;
                el.focus();
                break;
            }
        });

        // (2) Tab cycling — window-level listener so the user can't
        //     accidentally Tab past the drawer once `inert` has
        //     detached the main column from tab order.
        const onKey = (e: KeyboardEvent) => {
            if (e.key !== 'Tab' || !drawerRef.current) return;
            const focusables = Array.from(
                drawerRef.current.querySelectorAll<HTMLElement>(
                    'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )
            );
            if (focusables.length === 0) return;
            const first = focusables[0];
            const last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };
        window.addEventListener('keydown', onKey);

        return () => {
            window.cancelAnimationFrame(initialFocusRaf);
            window.removeEventListener('keydown', onKey);
        };
    }, [mobileOpen]);

    // Phase 06g — `inert` attribute on the main column.
    //
    // The `inert` attribute removes both the element AND its descendants
    // from tab order + a11y tree. React 19 supports `inert` as a JSX prop;
    // this project runs React 18 + @types/react 18.3 (verified in
    // package.json) where `inert` isn't on HTMLAttributes — so we toggle
    // the attribute imperatively via ref. Behaviour is identical and
    // safer across SSR boundaries (React 18 doesn't apply attributes for
    // unknown props anyway). We mirror `mobileOpen` for symmetry: on
    // desktop the drawer is a permanent aside, the attribute is harmless
    // whether present or absent.
    useEffect(() => {
        const el = mainColumnRef.current;
        if (!el) return;
        if (mobileOpen) {
            el.setAttribute('inert', '');
        } else {
            el.removeAttribute('inert');
        }
    }, [mobileOpen]);

    // Phase 06g — swipe-to-close pointer handlers.
    //
    // Each handler is intentionally narrow:
    //
    //   `onDrawerPointerDown` captures the starting position and
    //     pointer id. Skips mouse pointers (mouse drag is rare on
    //     mobile; we don't want to interfere with click-and-hold or
    //     text-selection). Skips pointerdowns on a / button descendants
    //     so nav-link clicks still navigate normally.
    //
    //   `onDrawerPointerMove` throttles dragOffset updates to one per
    //     animation frame so we never exceed React's render budget.
    //     Offset is clamped to `Math.min(0, dx)` — leftward only.
    //
    //   `onDrawerPointerUp` decides close-vs-snap-back. Threshold:
    //     dx < -50px (50% of typical 280px drawer) OR velocityX < -0.4 px/ms.
    //     Matches Material Design swipe-to-dismiss semantics.
    //
    // `touch-action: pan-y` on the aside permits vertical scrolling
    // inside the nav region but lets the browser know horizontal
    // gestures belong to our JS — preventing iOS Safari's native
    // "back" swipe from intercepting and going back in history.
    const onDrawerPointerDown = useCallback((e: React.PointerEvent<HTMLElement>) => {
        if (!mobileOpen) return;
        if (e.pointerType === 'mouse') return; // touch + pen only
        const target = e.target as HTMLElement | null;
        if (target?.closest('a, button')) return; // don't capture link clicks
        dragSessionRef.current = {
            startX: e.clientX,
            startT: Date.now(),
            pointerId: e.pointerId,
        };
        try {
            (e.currentTarget as Element).setPointerCapture(e.pointerId);
        } catch (_) { /* capture may fail on detached element */ }
        setIsDragging(true);
    }, [mobileOpen]);

    const onDrawerPointerMove = useCallback((e: React.PointerEvent<HTMLElement>) => {
        const session = dragSessionRef.current;
        if (!session || session.pointerId !== e.pointerId) return;
        const dx = e.clientX - session.startX;
        // Cancel any pending RAF before scheduling a new one — this
        // caps state updates to one per frame regardless of input rate.
        if (dragRafRef.current !== null) {
            window.cancelAnimationFrame(dragRafRef.current);
        }
        dragRafRef.current = window.requestAnimationFrame(() => {
            // Clamp: positive dx (rightward drag) is not a closing gesture.
            setDragOffset(Math.min(0, dx));
        });
    }, []);

    const onDrawerPointerUp = useCallback((e: React.PointerEvent<HTMLElement>) => {
        const session = dragSessionRef.current;
        if (!session || session.pointerId !== e.pointerId) return;
        const dx = e.clientX - session.startX;
        const dt = Date.now() - session.startT;
        const velocity = dt > 0 ? dx / dt : 0; // px/ms (negative = leftward)
        dragSessionRef.current = null;
        setIsDragging(false);
        if (dragRafRef.current !== null) {
            window.cancelAnimationFrame(dragRafRef.current);
            dragRafRef.current = null;
        }
        setDragOffset(0);
        try {
            (e.currentTarget as Element).releasePointerCapture(e.pointerId);
        } catch (_) { /* release may fail if pointer was already released */ }
        // Close if dragged more than 50px leftward OR flicked faster
        // than 0.4 px/ms leftward (matches Material Design).
        if (dx < -50 || velocity < -0.4) {
            setMobileOpen(false);
        }
    }, []);

    return (
        <div className="flex min-h-screen bg-gradient-to-br from-slate-50 via-white to-indigo-50/30 dark:from-gray-950 dark:via-gray-900 dark:to-slate-950">
            {/* Phase 06f — mobile backdrop. Click anywhere outside the
                drawer to close it. Mounted unconditionally on the same
                breakpoint that hides the drawer; below `lg` it sits
                between the page (z-auto) and the drawer (z-50).

                Phase 06f+1 — Backdrop's top edge starts at 60px so it
                leaves the topbar "warm" (visible + clickable) even when
                the drawer is open. Without this, the burger button (the
                natural close affordance) would be intercepted by the
                backdrop's z-40 plane and become unreachable. */}
            <div
                data-testid="admin-sidebar-backdrop"
                onClick={() => setMobileOpen(false)}
                aria-hidden="true"
                className={`fixed bottom-0 left-0 right-0 top-[60px] z-40 bg-gray-900/60 backdrop-blur-sm transition-opacity duration-200 ease-out lg:inset-0 lg:hidden ${
                    mobileOpen ? 'pointer-events-auto opacity-100 motion-safe:animate-fade-in' : 'pointer-events-none opacity-0'
                }`}
            />

            {/* Left sidebar — role-aware; AdminSidebar receives the user
                so it can render 5 role-grouped sections. Administrator
                sees every section (own highlighted, others muted); non-admin
                roles see only their own section.

                Phase 06f — Mobile (`< lg`) renders this as a fixed-position
                drawer (off-canvas, slides in from the left). Desktop
                (`>= lg`) flips it back into flex layout via `lg:relative`
                and uses the `collapsed`-driven `sidebarWidth` instead of
                the drawer-only `w-[280px]`. The transform is forced to
                `translate-x-0` on `lg:` so a stale `mobileOpen=true` can't
                pin the drawer off-screen on a freshly-resized window.

                Phase 06f+1 — Drawer's top edge is `top-[60px]` (not
                `inset-y-0`) so it leaves the sticky topbar fully visible
                and interactive. Without this lift, the drawer's full-viewport
                height would overlap the topbar visuals (logo + chrome) and
                the burger button — the natural close target — would be
                wedged under the drawer's `<aside>`. */}
            <aside
                id="admin-sidebar"
                ref={drawerRef}
                data-testid="admin-sidebar"
                aria-hidden={!mobileOpen}
                onPointerDown={onDrawerPointerDown}
                onPointerMove={onDrawerPointerMove}
                onPointerUp={onDrawerPointerUp}
                onPointerCancel={onDrawerPointerUp}
                style={isDragging ? { transform: `translateX(${dragOffset}px)`, transition: 'none' } : undefined}
                className={[
                    // Mobile-first: drawer shape, leaves the topbar warm.
                    // `touch-action: pan-y` permits vertical pan inside the
                    // nav region but routes horizontal drags to our pointer
                    // handlers — preventing iOS Safari's native swipe-back.
                    'fixed bottom-0 left-0 top-[60px] z-50 flex flex-col w-[280px] touch-pan-y',
                    // Desktop (`>= lg`): rejoin flex layout, z-auto, flex width
                    'lg:relative lg:top-auto lg:bottom-auto lg:z-auto lg:shrink-0',
                    collapsed ? 'lg:w-[72px]' : 'lg:w-[260px]',
                    // Visual chrome (used on both viewports)
                    'border-r border-gray-200/60 bg-white dark:border-gray-800/60 dark:bg-gray-900',
                    // Animate transform on mobile only — suppressed during
                    // an active drag so the drawer follows the finger with
                    // zero spring-back. Inline `style` overrides this.
                    isDragging ? '' : 'transition-transform duration-300 ease-in-out',
                    // Default mobile transform when not actively dragging.
                    !isDragging && (mobileOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'),
                    'lg:translate-x-0 lg:shadow-none',
                ].join(' ')}
                data-swipe-dragging={isDragging ? 'true' : 'false'}
            >
                <AdminSidebar
                    collapsed={collapsed}
                    onToggleCollapse={toggleCollapse}
                    onClose={() => setMobileOpen(false)}
                    activeRole={user.activeRole}
                    activeGroup={user.activeGroup}
                    hiddenRouteNames={gatedNavRoutes ?? []}
                />
            </aside>

            {/* Main column: topbar + content + footer.
                Phase 06g — `ref={mainColumnRef}` lets the layout toggle
                the `inert` HTML attribute imperatively via useEffect.
                When the drawer is open on mobile, the entire main column
                is removed from the tab order AND from the screen-reader
                tree, so a user can't Tab past the drawer's last focusable
                into background content. On desktop (`>= lg`) this is a
                no-op. The useEffect lives above the JSX. */}
            <div
                ref={mainColumnRef}
                className="flex min-w-0 flex-1 flex-col"
            >
                {/* Top header (admin variant) */}
                <header className="sticky top-0 z-30 flex h-[60px] items-center justify-between gap-3 border-b border-gray-200/60 bg-white/80 px-4 backdrop-blur-md dark:border-gray-800/60 dark:bg-gray-900/80 sm:gap-4 sm:px-6">
                    <div className="flex min-w-0 items-center gap-2 sm:gap-4">
                        {/* Phase 06f — Hamburger toggle (only visible below `lg`).
                            Toggles the off-canvas drawer. Acts as the canonical
                            open/close affordance on every viewport narrower than
                            the desktop sidebar breakpoint.

                            Phase 06g — `ref={burgerRef}` lets the focus-trap
                            close-path restore focus to this exact element on
                            drawer dismiss. */}
                        <button
                            ref={burgerRef}
                            type="button"
                            onClick={() => setMobileOpen((prev) => !prev)}
                            aria-controls="admin-sidebar"
                            aria-expanded={mobileOpen}
                            aria-label={mobileOpen ? 'Close sidebar' : 'Open sidebar'}
                            title={mobileOpen ? 'Close sidebar' : 'Open sidebar'}
                            data-testid="admin-sidebar-toggle"
                            className="touch-manipulation rounded-md p-2 text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-red/40 lg:hidden dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
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
                                {mobileOpen ? (
                                    // X icon when open
                                    <><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></>
                                ) : (
                                    // Hamburger icon when closed
                                    <><line x1="4" y1="6" x2="20" y2="6" /><line x1="4" y1="12" x2="20" y2="12" /><line x1="4" y1="18" x2="20" y2="18" /></>
                                )}
                            </svg>
                        </button>

                        {/* Phase 06d.2 — HeadlessUI Combobox replaces the disabled-input placeholder */}
                        <div className="relative hidden lg:block">
                            <GlobalSearch results={searchIndex ?? []} />
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {/* Phase 06e — Light/Dark theme toggle. */}
                        <ThemeToggle />

                        <RoleBadge role={user.activeRole} />

                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="rounded-md border border-gray-200 bg-white/70 px-2.5 py-1 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800/70 dark:text-gray-200"
                            data-testid="admin-logout-button"
                        >
                            Log Out
                        </Link>
                    </div>
                </header>

                {/* Page header band (Existing AdminHeader from Dashboard.tsx goes here) */}
                {header && (
                    <div
                        className="motion-safe:animate-fade-in border-b border-gray-200/60 bg-white/70 px-6 py-6 backdrop-blur-md dark:border-gray-800/60 dark:bg-gray-900/70"
                        data-testid="admin-header-band"
                    >
                        {header}
                    </div>
                )}

                {/* Main content */}
                <main
                    className="motion-safe:animate-fade-in min-w-0 flex-1 px-6 py-8 sm:px-8 sm:py-10 lg:px-10 lg:py-12"
                    data-testid="admin-main-content"
                >
                    {children}
                </main>

                {/* Footer (footer prop optional; 06d.2 will use FooterCard) */}
                {footer && (
                    <footer
                        className="border-t border-gray-200/60 bg-white/70 px-6 py-6 backdrop-blur-md dark:border-gray-800/60 dark:bg-gray-900/70"
                        data-testid="admin-footer"
                    >
                        {footer}
                    </footer>
                )}
            </div>
        </div>
    );
}
