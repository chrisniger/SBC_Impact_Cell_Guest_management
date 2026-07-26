# Phase 06b / 06c — UI Polish

> **Goal:** Elevate the entire auth + dashboard experience from "Breeze boilerplate" to "professional SaaS" — consistent spacing, premium chrome, animated entrance, dark-mode parity, and shared primitives that look the same everywhere.
> **Status:** **Partially shipped (auth side)**. Roadmap for the rest is scoped below.
> **Read time:** ~10 min.
> **Last updated:** 2026-07-26 — created alongside Phase 06 polish prelude (Login + GuestLayout).

This is a **UI-only** pass. It does **not** change authorization logic, route maps, policy gates, or any of the 5 existing verifiers' expectations. It introduces:

1. **Phase 06b prelude — DONE.** Login.tsx + GuestLayout.tsx + /public/logos/. The reference design language lives here for everything that follows.
2. **Phase 06b foundation — NEXT.** Tailwind theme tokens, AuthenticatedLayout chrome, shared primitives (KPICard, StatusPill, EmptyState), design-system doc.
3. **Phase 06c pages — AFTER 06b.** Each authenticated page gets a polish pass in a strict order. DataTable is extracted mid-pass.

A new verifier (`scripts/verify_phase06b_run.php`) is added at the end of Phase 06b. **Existing verifiers (58/15/36/22/14) stay unchanged** — they assert on logical data, not on polish classes. New polish assertions go through `data-testid` selectors so future redesigns don't regress the verifier.

---

## 1. Design language (reference, established in prelude)

The polished Login + GuestLayout now define the design tokens every subsequent page uses. They are **not theme-extended yet** (Phase 06b §2 will pull them into `tailwind.config.js`) — for now they live inline:

| Token                     | Value (Tailwind 3.2)                                                             | Where used                                                                                  |
| ------------------------- | -------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| Brand accent              | `indigo-500` / `indigo-600` / `indigo-700`                                        | CTAs, links, focus rings, splashes                                                          |
| Base neutrals             | `slate-50` / `white` / `gray-900` (light) + `gray-950` / `gray-900` / `white` (dark) | surfaces, text                                                                              |
| Card surface              | `bg-white border-gray-200 shadow-[0_4px_20px_rgba(0,0,0,0.03)]`                  | every card / KPI / panel                                                                    |
| Card surface (dark)       | `dark:bg-gray-800 dark:border-gray-700`                                           | cards in dark mode                                                                          |
| Card radius               | `rounded-xl` (12px)                                                               | KPICard, table, every list panel                                                            |
| Soft shadow token name    | (to become `shadow-card` in tailwind.config.js)                                  | §2.1 of Phase 06b                                                                            |
| Pill tones                | `neutral` / `success` / `warn` / `danger` / `brand` / `info` (new, for Phase 06b) | StatusPill — `info` tone is new                                                            |
| Spacing rhythm            | 6 / 8 / 12 / 16 / 24                                                              | inter-card gaps, form fields, KPI rows                                                       |
| Animation                 | `motion-safe:animate-[fadeIn_0.4s_ease-out_…]` (when `not motion-safe`, no anim) | page mount; keyframes in `tailwind.config.js` after §2                                       |
| Icons                     | Heroicons v1 silhouettes inline SVG (no icon library dependency)                  | feature pills, form prefixes, status banners                                                |
| Pattern decorations       | `<svg><defs><pattern>` with currentColor + 5/7% opacity                           | brand panel grid, form-panel dots                                                           |
| Gradient headline         | `bg-gradient-to-r from-white via-blue-100 to-indigo-200 bg-clip-text text-transparent` | hero text in brand panel                                                                    |
| Animated glow             | `animate-[pulse_8s|10s|12s_ease-in-out_infinite]` (staggered)                     | brand panel orbs; `motion-safe:` guarded                                                    |### 1.1 Logo / asset handling
- Logos live in `/public/logos/{logo,logo1}.png` — served at `/logos/*.png` per Laravel's `public/` root convention.
- `/logos/logo1.png` is the **primary** logo (used in brand panel + small mobile header). `/logos/logo.png` is **currently unused** — see Open Question ② in § 7 for the leaning (drop vs. dark-mode variant).
- Other pages that need a logo use the `ApplicationLogo` SVG fallback WHERE no `/public/logos/` file is appropriate (auth layout chrome). Settings page admin logo switcher can come later.

---

## 2. Phase 06b foundation (next, single coherent PR)

### 2.1 Tailwind theme tokens (`tailwind.config.js`)
Promote ad-hoc utilities to named tokens so the rest of the app stops pasting arbitrary values:

```js
// diff against tailwind.config.js current state
theme: {
  extend: {
    boxShadow: {
      card:  '0 4px 20px rgba(0,0,0,0.03)',
      'card-hover': '0 8px 30px rgba(0,0,0,0.06)',
    },
    colors: {
      // Semantic aliases (consumed via bg-surface / text-on-surface)
    },
    keyframes: {
      fadeIn: { '0%': { opacity: '0', transform: 'translateY(6px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
      pulseSoft: { /* explicit-shape slow pulse for animation-[pulse_…] semantically */ },
    },
    animation: {
      'fade-in': 'fadeIn 0.4s ease-out',
    },
  },
},
```

After this lands, replace every `shadow-[0_4px_20px_rgba(0,0,0,0.03)]` site-wide with `shadow-card`.

### 2.2 `AuthenticatedLayout.tsx` (universal chrome)
The single highest-leverage file — every authenticated page renders through it.

- **Top-bar:** Add `bg-white/80 backdrop-blur` for glassmorphism (matches brand panel). Tighten `h-16 → h-[60px]`. Logo gets a subtle `transition-transform hover:scale-105` group-hover.
- **Role badge:** Promote from inline dropdown trigger to a proper prominent chip **next to the user name** with a colored ring stroke matching the active role (the per-role palette already in `RoleBadge.tsx`).
- **Nav links:** Add subtle hover underline transition + animated indicator bar (like `Linear`). Default to top-bar only — **drop the sidebar idea** from earlier drafts so data-heavy pages keep full width.
- **Header band:** `bg-white dark:bg-gray-800` → `bg-white/80 dark:bg-gray-900/80 backdrop-blur border-b border-gray-200/60` for premium glass.
- **Content max-width:** `max-w-7xl` is fine for 1280. Move to `max-w-[88rem]` for `2xl:` (1440+) so dashboards breathe.
- **Entrance animation:** Wrap `{children}` in a `motion-safe:animate-fade-in` div (uses theme.extend.animation token from §2.1).
- **Mobile:** Already works. Just add a subtle backdrop blur to the responsive dropdown.

### 2.3 Shared primitives

#### 2.3.1 `KPICard.tsx`
Add **backward-compatible** optional props:
- `delta?: { value: number; positiveIsGood?: boolean }` — renders a small `▲ / ▼` + the change number in `text-emerald-600` or `text-rose-600` next to the caption.
- `variant?: 'default' | 'glass'` — default preserves current rendering; glass adds `bg-white/70 backdrop-blur`.
- `accent?: 'indigo' | 'emerald' | 'amber' | 'rose'` — tints the big number color; default is `text-gray-900`.

All five current callers in `Dashboard.tsx` keep working without changes (defaults match the existing call shape).

#### 2.3.2 `StatusPill.tsx`
- Add `info` tone (blue) for "scheduled" / "pending" / "neutral-positive" markers (used by Impact Cells and Reports later).
- Add optional leading `dot: boolean` and an optional `size: 'sm' | 'md'` prop.
- Accept `className?: string` override for sizing/context.

#### 2.3.3 NEW: `EmptyState.tsx`
Idempotent. Icon (inline SVG slot), title, sub-message, optional CTA slot. Replaces the inline divs in Dashboard.tsx, ImpactSubmissions/Index.tsx, Guests/Index.tsx.

#### 2.3.4 NEW (Phase 06b, NOT 06c): no `DataTable` extraction YET.
`DataTable` is deferred to 06c because it touches the most pages and is the highest regression risk. Building it after a couple of polished pages lets us see the real prop surface.

### 2.4 Accessibility baseline
- All decorative SVGs: `aria-hidden="true"` (already done in Login + GuestLayout; audit & re-apply across components).
- All `motion-*` animations: wrap every `animate-pulse`, `animate-fade-in`, `hover:-translate-y-` with `motion-safe:` prefix. Default to NO animation if `prefers-reduced-motion: reduce`.
- Focus rings: keep the existing `focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2` pattern. Add `focus-visible:` variants where appropriate (Login + GuestLayout do this).

### 2.5 Verifier for Phase 06b (`scripts/verify_phase06b_run.php`)
Asserts — **without coupling to specific CSS classes** — that:
- `/login` renders cleanly (200, no console-thrown React error markers).
- `/dashboard` renders for each of the 4 seeded dashboard-role users (officer1, team1, followupAdmin, sbcadmin).
- KPICard, StatusPill, EmptyState, AuthenticatedLayout export the typed React components and accept the new optional props.
- Each `data-testid` we add (`kpi-pending-contacts`, `pill-status`, `empty-state`, `nav-dashboard`, etc.) is present in its expected mount point.

**Existing verifiers stay untouched.** New polish assertions go through `data-testid` so future redesigns don't break assertions on incidental classes.

---

## 3. Phase 06c — pages pass (after 06b)

Strict order so each PR is reviewable in isolation. Heroicons inline stays the rule (no extra dep).

### 3.1 `Dashboard.tsx` (5 variants: officer, team, impactCell, zonal, admin)
- Add entrance animation per KPI row.
- Section dividers (vertical `border-l-2 border-indigo-500/30` + section label) between KPI / Queue / Quick Submit / Recent.
- Sticky filter bar where relevant (officer queue gets a search input).
- `LeaderDashboard` + `ZonalDashboard` get symmetric KPI cards (missing today).

### 3.2 `Guests/Index.tsx`
- Filter chips (Follow Up Status / Contacted Status / Impact Status).
- Column-visibility toggle.
- Sticky header (`sticky top-[60px]` under the chrome).
- Hover row highlight + selected-row state.

### 3.3 `Guests/Show.tsx`
- Convert flat list into section cards: Contact Info / Follow Up / Visit / Relationships.
- Pull `ContactsTimeline` into its own card with the section header.
- Each card: icon + title + ring + body content; dark-mode parity.

### 3.4 `Guests/Edit.tsx`
- Already defensive; add:
  - progress dot strip at top (which step of the form the user is on).
  - Save-state indicator (idle / saving / saved) with subtle pulse.
  - Dirty-state guard: warn if navigating away with unsaved changes.

### 3.5 `ImpactCells/{Index,Show}.tsx`
- Index: full-tree visual (cards or a proper hierarchy diagram).
- Show: breadcrumbs back to index; clean sub-cells list.
- Add a hero band on Show with cell name, parent (if any), and primary-cell badge.

### 3.6 `ImpactSubmissions/*`
- `Create.tsx`: wizard style + side-rail live preview.
- `Index.tsx`: tighter table polish; filter chips.
- `MyReports.tsx`: timeline layout.
- `Show.tsx`: read-only polished card.
- `SoulSearch.tsx`: search-as-you-type with subtle highlight on match.

### 3.7 Lower-priority pages (token refresh only in 06c; full polish in 06d if needed)
- `Reports/Index.tsx`
- `Audit/Index.tsx`
- `Csv/Import.tsx`
- `Notifications/Settings.tsx`
- `Welcome.tsx` (unauthenticated entry)

### 3.8 DataTable extraction (mid-06c)
Refactor with deliberate duplication of call sites **first**, then extract when the prop surface stabilizes. Suggested order:
1. `Guests/Index.tsx` — most columns, hover, pagination, filter chips.
2. `ImpactSubmissions/Index.tsx` — fewer columns, type discriminator.
3. `Dashboard.tsx` queue tables (officer + team).
4. `MyReports.tsx` — diffs vs step 3.

### 3.9 Verifier for Phase 06c (`scripts/verify_phase06c_run.php`)
- `data-testid` per page (`page-guests-index`, `page-dashboard-officer`, etc.).
- Empty-state copy rendered for each empty permission slice.
- Cross-cutting: Every authenticated page routes successfully under the right seeded user (no 500s).

---

## 4. Risks (top 5 from the design review)

1. **Verifier regression on Dashboard.** Refactoring `KPICard` internals could orphan DOM string assertions in `verify_phase05_run.php` / `verify_phase06_run.php`. Mitigation: `data-testid` for verifiers + keep caption text unchanged (`caption.toUpperCase()` invariant). Polish passes WILL audit `verify_phase05_run.php` to confirm zero impact.
2. **Horizontal overflow on table-heavy pages.** New section cards / spacing rhythm can push `Reports` / `Audit` tables beyond `max-w-7xl`. Mitigation: add `overflow-x-auto` wrappers around every table by default; test at 1024px / 1440px breakpoints.
3. **PR size.** Combining chrome + primitives + pages in one large PR will be hard to review. Mitigation: Phase 06b is single PR (chrome + primitives); Phase 06c is **3 sub-PRs** (`a. Dashboard`, `b. Guests + Cells`, `c. Submissions + Reports`).
4. **Dark mode parity.** Tones like `bg-emerald-900/40` work but new `info` and accent colors can fail in dark mode. Mitigation: every new token gets tested under both modes via Phase 06b verifier.
5. **`prefers-reduced-motion` already absent.** All new animations get `motion-safe:` guards; a sweep of existing `animate-pulse` / fadeIn gets the same guard. If we forget a `motion-safe:`, the page violates WCAG 2.3.3.

---

## 5. Files touched (estimate)

| Phase   | Files (approximate)                                                                                            |
| ------- | -------------------------------------------------------------------------------------------------------------- |
| 06b     | `tailwind.config.js`, `resources/css/app.css`, `resources/js/Layouts/AuthenticatedLayout.tsx`, `Components/{KPICard,StatusPill}.tsx`, `Components/EmptyState.tsx` (NEW), `scripts/verify_phase06b_run.php` (NEW). Net: ~5 files. |
| 06c-a   | `Pages/Dashboard.tsx`. 1 file.                                                                                 |
| 06c-b   | `Pages/Guests/{Index,Show,Edit}.tsx`, `Pages/ImpactCells/{Index,Show}.tsx`, `Components/{DataTable}.tsx` (NEW). 5 files. |
| 06c-c   | `Pages/ImpactSubmissions/{Create,Index,MyReports,Show,SoulSearch}.tsx`, `Pages/{Reports,Audit,Csv,Notifications}/…`, `Pages/Welcome.tsx`. ~9 files. |
| 06c-verify | `scripts/verify_phase06c_run.php` (NEW). 1 file.                                                          |

---

## 6. Definition of done (per phase)

**Phase 06b (next):**
- [ ] `tailwind.config.js` has named boxShadow + fadeIn keyframe + animation tokens.
- [ ] `AuthenticatedLayout` ships the glassmorphism top-bar + role-chip + nav indicator + content `max-w-[88rem]`.
- [ ] `KPICard` + `StatusPill` have new optional props; all 5 dashboard call sites still pass without changes.
- [ ] `EmptyState` typed component exists and is used in `Dashboard.tsx` empty paths.
- [ ] `verify_phase06b_run.php` runs **10 pass / 0 fail** (target — refine when actual sub-assertions settle). Existing 5 verifiers still 58/15/36/22/14.

**Phase 06c:**
- [ ] All 5 dashboard variants have entrance animations + section dividers.
- [ ] `Guests/{Index,Show,Edit}` and `ImpactCells/{Index,Show}` fully polished with section cards.
- [ ] `ImpactSubmissions/*` polished (Create wizard + timeline).
- [ ] Heroicons inline SVG throughout. No new icon-library dependency.
- [ ] `data-testid` per page present.
- [ ] `verify_phase06c_run.php` runs **15 pass / 0 fail** (target — refine when actual sub-assertions settle). All 6 prior verifiers still pass.

---

## 7. Open questions

① **Should `Welcome.tsx` (unauthenticated) get the same brand panel treatment as Login?** Leaning **YES** so the marketing surface matches the auth surface — consider in 06c token refresh.

② **The unused `/logos/logo.png` (vs the primary `/logos/logo1.png`) — drop or use as dark-mode variant?** Lean toward reserving it for a future dark-mode-safe logo swap; do not delete. (Resolved during 06c 1.1 review: § 1.1 now points here instead of duplicating the question.)

③ **Zonal variant (`Impact_Zonal_Cordinator`) is in scope for 06c but should ship as a sub-PR aligned with Phase 07 (Cell Leader forms) which is its natural group.**
