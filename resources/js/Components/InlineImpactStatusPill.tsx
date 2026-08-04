import { patchJson } from '@/lib/http';
import { usePopperMenu } from '@/lib/usePopperMenu';
import { createPortal } from 'react-dom';
import { useEffect, useRef, useState } from 'react';

/**
 * Phase 07c — Inline pill editor for the Guest.impact_status column,
 *
 * Used by the Leader Dashboard's assigned-guests table (see Dashboard.tsx
 * LeaderDashboard). Options per Implementation/Phase_07_Impact_Cell_Leader.md
 * § 9: Contacted / Not Contacted / Not Reachable + an explicit Clear option.
 *
 * Mirrors the existing `updateFollowUpStatus` PATCH pattern in spirit:
 *  - lightweight JSON endpoint (no Inertia page reload)
 *  - optimistic UI: local state updates immediately, rolls back on error
 *  - GuestPolicy::update() gates per-row on the server side (impactCell
 *    user can update only when nearest_impact_cell_id matches), so a
 *    non-editor here gets `canEdit={false}` and the dropdown never opens
 *
 * 2026-08-04 — the dropdown menu is rendered through a React portal attached
 * to `document.body` with `position: fixed` coordinates derived from the
 * trigger's bounding rect. Previously the menu was absolutely positioned
 * inside the trigger wrapper, so any `overflow: auto/hidden` ancestor (the
 * Assigned Guests modal's scrollable table body, dashboard tables, etc.)
 * clipped it and forced an unwanted scrollbar. The portal lifts the menu
 * out of every clipping container, matching the pattern used by GitHub /
 * Airtable / Linear:
 *  - never clipped by the modal (or any overflow container)
 *  - floats above the modal (z-[100] > the HeadlessUI Dialog z-50)
 *  - auto-flips ABOVE the trigger when there isn't enough room below
 *  - closes on outside-click, page scroll, and window resize so a fixed
 *    menu can never drift away from its trigger
 *
 * The positioning + dismissal logic lives in the shared `usePopperMenu`
 * hook (resources/js/lib/usePopperMenu.ts) so future custom dropdowns
 * (follow-up status, cell pickers, …) reuse it verbatim.
 */

type Status = 'Contacted' | 'Not Contacted' | 'Not Reachable';

const STATUSES: { value: Status; full: string; tone: string }[] = [
    { value: 'Contacted',      full: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',  tone: 'emerald' },
    { value: 'Not Contacted',  full: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',         tone: 'amber' },
    { value: 'Not Reachable',  full: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',             tone: 'rose' },
];

const neutralCls = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';

/** w-44 = 11rem = 176px — keep in sync with the menu's className width. */
const MENU_WIDTH = 176;

function dotClass(tone: string): string {
    // The first class in STATUSES[i].full is the bg-*; pull it out for the
    // colored marker dot in the dropdown menu.
    switch (tone) {
        case 'emerald': return 'bg-emerald-500';
        case 'amber':   return 'bg-amber-500';
        case 'rose':    return 'bg-rose-500';
        default:        return 'bg-gray-400';
    }
}

export default function InlineImpactStatusPill({
    guestId,
    current,
    canEdit,
}: {
    guestId: string;
    current: string | null;
    canEdit: boolean;
}) {
    const [status, setStatus] = useState<string | null>(current);
    // 2026-08-04 — transient "Status updated" confirmation shown after a
    // successful PATCH (the endpoint is plain JSON, so we call it with fetch
    // instead of Inertia's router — see resources/js/lib/http.ts).
    const [saved, setSaved] = useState(false);
    const savedTimer = useRef<number | null>(null);

    // Shared portal-dropdown positioning hook — open/pos state, refs,
    // flip-up placement, outside-click + scroll/resize dismissal. Passing
    // `enabled: canEdit` makes toggle() a no-op for read-only rows.
    const { open, pos, triggerRef, menuRef, toggle, close } = usePopperMenu({
        enabled: canEdit,
        width: MENU_WIDTH,
    });

    // Sync from props when parent re-fetches (e.g. after a successful PATCH
    // round-trip Inertia re-renders this component with fresh server data).
    useEffect(() => {
        setStatus(current);
    }, [current]);

    // Clear the confirmation timer on unmount.
    useEffect(() => () => {
        if (savedTimer.current) window.clearTimeout(savedTimer.current);
    }, []);

    function handleSelect(v: string | null) {
        const prev = status;
        setStatus(v);
        close();
        if (!canEdit) return;

        // Send `null` explicitly on Clear — the server validator accepts
        // null (nullable|string|max:64), and this keeps the DB column in
        // a true three-state shape (Contacted / Not Contacted / Not
        // Reachable / NULL). Sending an empty string here would also be
        // coerced server-side (GuestController::updateImpactStatus strips
        // '' → null) but the explicit `null` is cleaner at the source.
        //
        // 2026-08-04 — plain fetch (patchJson), NOT router.patch: the
        // endpoint returns a plain JSON body and Inertia's router rejects
        // non-Inertia responses with a full-screen error modal.
        patchJson(`/guests/${guestId}/impact-status`, { impact_status: v })
            .then(() => {
                setSaved(true);
                if (savedTimer.current) window.clearTimeout(savedTimer.current);
                savedTimer.current = window.setTimeout(() => setSaved(false), 2000);
            })
            .catch(() => setStatus(prev));
    }

    // Portal menu — fixed to the viewport, z-[100] so it floats above the
    // HeadlessUI Modal Dialog (z-50) and every table row, and detached from
    // all overflow containers so nothing can clip it. Position comes from
    // the shared usePopperMenu hook (flip-up + viewport clamping included).
    const portalMenu = open && canEdit && typeof document !== 'undefined'
        ? createPortal(
            <div
                ref={menuRef}
                role="menu"
                style={{ top: pos.top, left: pos.left, width: MENU_WIDTH }}
                className="fixed z-[100] overflow-hidden rounded-lg border border-gray-200 bg-white shadow-card-hover dark:border-gray-700 dark:bg-gray-800"
            >
                <div className="py-1">
                    {STATUSES.map((s) => (
                        <button
                            key={s.value}
                            type="button"
                            role="menuitem"
                            onClick={() => handleSelect(s.value)}
                            data-testid={`impact-status-option-${s.value.toLowerCase().replace(/\s+/g, '-')}`}
                            className="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-medium text-gray-700 transition-colors hover:bg-indigo-50/60 dark:text-gray-200 dark:hover:bg-gray-700/60"
                        >
                            <span className={`inline-block h-2 w-2 rounded-full ${dotClass(s.tone)}`} aria-hidden="true" />
                            {s.value}
                        </button>
                    ))}
                    {status !== null && (
                        <>
                            <div className="my-1 border-t border-gray-100 dark:border-gray-700" aria-hidden="true" />
                            <button
                                type="button"
                                role="menuitem"
                                onClick={() => handleSelect(null)}
                                data-testid="impact-status-clear"
                                className="block w-full px-3 py-1.5 text-left text-xs text-gray-500 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/60"
                            >
                                Clear status
                            </button>
                        </>
                    )}
                </div>
            </div>,
            document.body,
        )
        : null;

    const pillCls = STATUSES.find((s) => s.value === status)?.full ?? neutralCls;
    const label = status ?? 'Set status';

    return (
        <div ref={triggerRef} className="relative inline-flex items-center gap-2">
            <button
                type="button"
                onClick={toggle}
                disabled={!canEdit}
                data-testid={`impact-status-pill-${guestId}`}
                aria-haspopup={canEdit ? 'menu' : undefined}
                aria-expanded={canEdit ? open : undefined}
                className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold transition-colors ${pillCls} ${
                    canEdit ? 'cursor-pointer hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-800' : 'cursor-default'
                }`}
            >
                {label}
                {canEdit && (
                    <svg viewBox="0 0 20 20" fill="currentColor" className="h-3 w-3 opacity-60" aria-hidden="true">
                        <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                    </svg>
                )}
            </button>

            {/* 2026-08-04 — transient success confirmation after a status
                update lands. Auto-dismisses after ~2s. */}
            {saved && (
                <span
                    role="status"
                    data-testid="impact-status-saved"
                    className="motion-safe:animate-fade-in inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
                >
                    <svg viewBox="0 0 20 20" fill="currentColor" className="h-3 w-3" aria-hidden="true">
                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                    </svg>
                    Status updated
                </span>
            )}

            {portalMenu}
        </div>
    );
}
