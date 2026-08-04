import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';

/**
 * usePopperMenu — shared portal-dropdown positioning hook (usePopper-style).
 *
 * Extracted 2026-08-04 from InlineImpactStatusPill so future custom
 * dropdowns (follow-up status, cell pickers, etc.) reuse the exact
 * portal + fixed-positioning behavior that was verified in a real browser:
 *
 *  - The menu is rendered by the CALLER through a React portal attached to
 *    `document.body` (see createPortal usage in InlineImpactStatusPill), so
 *    no `overflow: auto/hidden` ancestor — modals, table bodies, cards — can
 *    clip it, and opening the menu never forces a scrollbar.
 *  - Coordinates are fixed viewport px derived from the trigger's bounding
 *    rect at open time.
 *  - Auto-flips ABOVE the trigger when there isn't enough room below it.
 *  - Horizontally clamped to the viewport (with an inset safety margin that
 *    guards the degenerate narrow-viewport case).
 *  - Closes on outside-click (checks BOTH the trigger and the portaled menu,
 *    since the menu is not a DOM descendant of the trigger), on page scroll
 *    (capture phase, so scrolls inside overflow containers also count), and
 *    on window resize — a fixed menu must never drift from its anchor.
 *
 * Usage:
 *   const { open, pos, triggerRef, menuRef, toggle, close } =
 *       usePopperMenu({ enabled: canEdit, width: MENU_WIDTH });
 *
 *   <div ref={triggerRef}>
 *     <button onClick={toggle}>…</button>
 *     {open && createPortal(
 *       <div ref={menuRef} style={{ top: pos.top, left: pos.left, width: MENU_WIDTH }}
 *            className="fixed z-[100] …">…</div>,
 *       document.body,
 *     )}
 *   </div>
 */

export interface PopperMenuOptions {
    /** When false, toggle() is a no-op (e.g. view-only mode). Default true. */
    enabled?: boolean;
    /** Menu width in px — used for horizontal viewport clamping. */
    width?: number;
    /** Vertical gap between the trigger and the menu in px. Default 6. */
    gap?: number;
    /** Minimum inset from the viewport edges in px. Default 8. */
    inset?: number;
}

export interface PopperMenuState {
    /** Whether the menu is currently open. */
    open: boolean;
    /** Fixed viewport coordinates for the portaled menu (top-left). */
    pos: { top: number; left: number };
    /** Attach to the trigger wrapper element (the menu's anchor). */
    triggerRef: React.RefObject<HTMLDivElement>;
    /** Attach to the portaled menu element (for flip-up measuring + outside-click). */
    menuRef: React.RefObject<HTMLDivElement>;
    /** Toggle the menu; captures the trigger rect for placement on open. */
    toggle: () => void;
    /** Close the menu. */
    close: () => void;
    /** Imperative open/close setter (e.g. from a select handler). */
    setOpen: (open: boolean) => void;
}

const DEFAULT_GAP = 6;
const DEFAULT_INSET = 8;

export function usePopperMenu({
    enabled = true,
    width = 176,
    gap = DEFAULT_GAP,
    inset = DEFAULT_INSET,
}: PopperMenuOptions = {}): PopperMenuState {
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState<{ top: number; left: number }>({ top: 0, left: 0 });
    const triggerRef = useRef<HTMLDivElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);
    // Anchor rect captured at open time; used by the flip-up layout pass.
    const anchorRect = useRef<DOMRect | null>(null);

    /** Clamp a left coordinate so the menu stays fully inside the viewport.
     *  The outer Math.max(inset, …) guards the degenerate narrow-viewport
     *  case where innerWidth - width - inset goes negative. */
    const clampLeft = useCallback(
        (left: number) => Math.max(
            inset,
            Math.min(Math.max(inset, left), window.innerWidth - width - inset),
        ),
        [inset, width],
    );

    const toggle = useCallback(() => {
        if (!enabled) return;
        anchorRect.current = triggerRef.current?.getBoundingClientRect() ?? null;
        if (anchorRect.current) {
            // Immediate below-the-trigger placement; the layout pass below
            // refines to flip-up when needed. Both writes land before the
            // next paint, so there's no visible jump.
            setPos({
                top: anchorRect.current.bottom + gap,
                left: clampLeft(anchorRect.current.left),
            });
        }
        setOpen((o) => !o);
    }, [enabled, gap, clampLeft]);

    const close = useCallback(() => setOpen(false), []);

    // If `enabled` flips to false while the menu is open (e.g. the consumer's
    // read-only gate turns off mid-session), close it — a floating menu with
    // no interactive trigger would otherwise linger until outside-click.
    useEffect(() => {
        if (!enabled) setOpen(false);
    }, [enabled]);

    // Final placement after the menu has rendered: flip ABOVE the trigger
    // when there isn't enough room below it, and clamp horizontally. Runs
    // before paint (useLayoutEffect), so the menu never visibly jumps.
    // Falls back to the live trigger rect when opened imperatively via
    // setOpen(true) (no anchor captured at toggle time).
    useLayoutEffect(() => {
        if (!open) return;
        const rect = anchorRect.current ?? triggerRef.current?.getBoundingClientRect() ?? null;
        if (!rect || !menuRef.current) return;
        const menuHeight = menuRef.current.offsetHeight;
        const spaceBelow = window.innerHeight - rect.bottom - gap;
        const top = spaceBelow >= menuHeight
            ? rect.bottom + gap
            : Math.max(inset, rect.top - menuHeight - gap);
        setPos({ top, left: clampLeft(rect.left) });
    }, [open, gap, inset, clampLeft]);

    // Close on outside-click. The menu lives in a portal on document.body,
    // so it is NOT a DOM descendant of the trigger wrapper — check BOTH refs
    // so clicks inside the open menu don't close it.
    useEffect(() => {
        if (!open) return;
        function onDown(e: MouseEvent) {
            const t = e.target as Node;
            if (
                (triggerRef.current && triggerRef.current.contains(t)) ||
                (menuRef.current && menuRef.current.contains(t))
            ) {
                return;
            }
            setOpen(false);
        }
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    // Close on page scroll / window resize: the menu is position:fixed and
    // would otherwise drift away from its trigger. Capture-phase scroll so
    // scrolls inside the modal's overflow container also close it.
    useEffect(() => {
        if (!open) return;
        function onViewportChange() { setOpen(false); }
        window.addEventListener('scroll', onViewportChange, true);
        window.addEventListener('resize', onViewportChange);
        return () => {
            window.removeEventListener('scroll', onViewportChange, true);
            window.removeEventListener('resize', onViewportChange);
        };
    }, [open]);

    return { open, pos, triggerRef, menuRef, toggle, close, setOpen };
}
