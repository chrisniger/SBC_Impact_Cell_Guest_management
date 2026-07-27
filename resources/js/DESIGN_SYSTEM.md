# DESIGN_SYSTEM — SBC Guest Portal (Laravel + Inertia + React)

> **Audience:** any developer touching `resources/js/Pages/**` or `resources/js/Components/**`.
> **Read time:** ~5 min.
> **Last shipped:** Phase 06c — UI polish (chrome + pages).
> **Status:** ✅ **Canonical.** Add new tokens here first, *then* reference them in components. The text below is the **only** colour-and-surface decision the codebase makes; do not introduce a new shade without updating this file.

This is the **shipped-design** reference (what's actually in the JSX right now). For the original design *intent* (Brand Red, Geist/Inter, sidebar idea that was dropped in favour of full-width data tables), see `Implementation/06_Dashboard_Design_System.md`.

---

## 0. TL;DR + the one rule

We're on **Tailwind v3 + the default `tailwind.config.js`** (no custom theme extension). Every colour decision is a **paired Tailwind utility + its `dark:` variant** drawn from the default palette. **There is no theme layer above Tailwind** — please don't add one.

```
Light: text-gray-900 bg-white border-gray-200
Dark:  text-gray-100 bg-gray-800 border-gray-700
```

When in doubt, copy a pair from this doc rather than inventing — that's the entire job of this file.

---

## 1. Source-of-truth files (where each token is used)

| Token family             | Canonical references                                          |
| ------------------------ | ------------------------------------------------------------- |
| Surface / borders / ink  | `resources/js/Layouts/AuthenticatedLayout.tsx`, `Layouts/GuestLayout.tsx` |
| Brand (Indigo)           | `Layouts/GuestLayout.tsx` (brand panel), `Components/KPICard.tsx` (accent), every Page's eyebrow `<p>` |
| Status pills (success/warn/danger/brand/info/neutral) | `resources/js/Components/StatusPill.tsx` (single source of truth) |
| KPI accents + deltas     | `resources/js/Components/KPICard.tsx`                         |
| Role badges              | `resources/js/Components/RoleBadge.tsx`                       |
| Form atoms (input/select/textarea) | `Components/TextInput.tsx` + every Page's `<input>` block    |
| Empty states             | `resources/js/Components/EmptyState.tsx`                      |
| Glassmorphism (top-bar, header) | `Layouts/AuthenticatedLayout.tsx`                       |
| Hero gradient bands      | `Pages/ImpactCells/Show.tsx`, `Pages/ImpactSubmissions/Show.tsx`, `Layouts/GuestLayout.tsx` |
| Glow orbs + SVG grid     | `Layouts/GuestLayout.tsx`, `Pages/Welcome.tsx`                 |
| Timeline rail + step circles | `Components/ContactsTimeline.tsx` (mounted on `Guests/Show` + `Guests/Edit`) |
| LeadershipBoard tile hover ring | `Components/LeadershipBoard.tsx` (mounted on Dashboard `LeaderDashboard`) |

**If you change one of these files, update the matching table in § 3–§ 10 below.**

---

## 2. Page coverage matrix

| Page                                  | Special tokens beyond § 3–§ 10                                                                      |
| ------------------------------------- | --------------------------------------------------------------------------------------------------- |
| `Pages/Auth/Login.tsx` (GuestLayout)  | § 6 (Login-only decoration — brand panel, glow orbs, SVG grid, hero text, status banner)            |
| `Pages/Welcome.tsx`                   | § 7 (Welcome-only decoration — violet accent, feature-card ring system, trust strip)                |
| `Pages/Dashboard.tsx` (5 variants)    | § 5 (KPI accent map), `bg-indigo-50` quick-submit icon tile, chart legends, `bg-indigo-600` active nav underline |
| `Pages/Guests/Index.tsx`              | § 3 standard + page-number-pill (`bg-indigo-600 text-white`)                                        |
| `Pages/Guests/Show.tsx`               | § 3 standard + `<Card>` inner-wash header band (`bg-gray-50/50 dark:bg-gray-900/40`)                 |
| `Pages/Guests/Edit.tsx`               | § 3 standard + sticky save bar (`sticky bottom-0 border-gray-200 bg-white/90 backdrop-blur-md`)     |
| `Pages/Reports/Index.tsx`             | § 8 (chart fills — **known gap**, see § 11)                                                         |
| `Pages/Audit/Index.tsx`               | § 3 standard + mono date cell (`font-mono`)                                                         |
| `Pages/Notifications/Settings.tsx`    | § 3 standard + `<textarea>` styling                                                                 |
| `Pages/Csv/Import.tsx`                | § 3 standard + drop-zone dashed border (`border-2 border-dashed border-gray-300 hover:border-indigo-400`) |
| `Pages/ImpactCells/Index.tsx`         | § 3 standard + corner-glow (`bg-indigo-50/60 opacity-0 group-hover:opacity-100 dark:bg-indigo-900/20`) |
| `Pages/ImpactCells/Show.tsx`          | § 6 hero gradient band + primary pill (`StatusPill tone="brand"`)                                   |
| `Pages/ImpactSubmissions/{Index,MyReports}.tsx` | § 3 standard + type pill (`STATUS_TONE[member|report|childbirth|soul] = info|success|warn|brand`) |
| `Pages/ImpactSubmissions/Create.tsx`  | § 3 standard + sticky save footer (same as Guests/Edit)                                             |
| `Pages/ImpactSubmissions/Show.tsx`    | § 6 hero gradient band                                                                              |
| `Pages/ImpactSubmissions/SoulSearch.tsx` | § 3 standard + magnifier-icon prefix in search input                                              |
| `Pages/Profile/Edit.tsx`              | § 3 standard + `<DeleteUserForm>` danger button (rose)                                              |

---

## 3. Neutral surface stack (page + card chrome)

The same nine classes are used on every authenticated page **and** the Login form panel.

| Token                  | Light hex    | Dark hex     | Tailwind classes                                                                                          |
| ---------------------- | ------------ | ------------ | --------------------------------------------------------------------------------------------------------- |
| Page gradient — start  | `#F8FAFC`    | `#030712`    | `from-slate-50 … dark:from-gray-950`                                                                      |
| Page gradient — mid    | `#FFFFFF`    | `#111827`    | `via-white … dark:via-gray-900`                                                                           |
| Page gradient — end    | `#EEF2FF/30` | `#020617`    | `to-indigo-50/30 … dark:to-slate-950`                                                                     |
| **Card surface**       | `#FFFFFF`    | `#1F2937`    | `bg-white … dark:bg-gray-800`                                                                             |
| Card hover surface     | `#F9FAFB`    | `#374151`    | `hover:bg-gray-50 … dark:hover:bg-gray-700`                                                               |
| **Inner wash**         | `#F9FAFB/50` | `#030712/40` | `bg-gray-50/50 … dark:bg-gray-900/40` (card headers, sticky footer bars above form actions)                |
| **Table head wash**    | `#F9FAFB/80` | `#111827/60` | `bg-gray-50/80 … dark:bg-gray-900/60`                                                                     |
| Row divider            | `#E5E7EB`    | `#374151`    | `divide-gray-200 … dark:divide-gray-700`                                                                  |
| **Row hover**          | `#EEF2FF/40` | `#374151/40` | `hover:bg-indigo-50/40 … dark:hover:bg-gray-700/40`                                                       |
| **Card border**        | `#E5E7EB`    | `#374151`    | `border-gray-200 … dark:border-gray-700` (KPICard, EmptyState, every form `<section>`)                    |
| Inner border (subtle)  | `#F3F4F6`    | `#374151`    | `border-gray-100 … dark:border-gray-700` (inside `<dl>` rows, card-header separators)                     |
| **Glassmorphism border** | `#E5E7EB/60` | `#1F2937/60` | `border-gray-200/60 … dark:border-gray-800/60` (sticky top-bar, header band, mobile menu)                 |
| Soft dashed border     | `#D1D5DB`    | `#4B5563`    | `border-gray-300 … dark:border-gray-600` (EmptyState dashed, CSV drop zone, QuickSubmit)                   |
| **Soft shadow**        | `rgba(0,0,0,.03)` | same    | `shadow-[0_4px_20px_rgba(0,0,0,0.03)]` (every card surface)                                              |
| **Hover shadow**       | `rgba(0,0,0,.06)` | same    | `hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)]` (KPI, ImpactCell tile, QuickLink)                           |

> Anything that "looks like a card" uses these nine tokens. **Do not introduce a fourth background class** for cards.

---

## 4. Brand & accent (Indigo — the only primary)

| Token                  | Light hex   | Dark hex     | Tailwind classes                                                          | Where it goes                                              |
| ---------------------- | ----------- | ------------ | ------------------------------------------------------------------------- | ---------------------------------------------------------- |
| `indigo-50`            | `#EEF2FF`   | same         | `bg-indigo-50`, `hover:bg-indigo-50/50`                                    | QuickSubmit tile, QuickLink hover, ImpactCell corner-glow   |
| `indigo-100`           | `#E0E7FF`   | same         | `bg-indigo-100`                                                            | CSV drop-zone circle, CSV file-info tile                   |
| `indigo-200`           | `#C7D2FE`   | same         | `border-indigo-200`, `bg-indigo-200`                                       | Welcome strip border, hover-ring on white Sign-in button   |
| `indigo-300`           | —           | `#A5B4FC`     | `dark:text-indigo-300`                                                     | KPI delta text dark, every section-header rail icon dark, every card-icon dark |
| `indigo-400`           | —           | `#818CF8`     | `dark:bg-indigo-400`                                                       | Hover state on Login **Sign in** primary button            |
| `indigo-500`           | `#6366F1`   | same         | `bg-indigo-500`                                                            | **Active-nav underline dot** (Dashboard), pulse dot on Welcome badge, checkbox tick (`text-indigo-600` on the box) |
| **`indigo-600`**       | `#4F46E5`   | `#818CF8`     | `bg-indigo-600 … dark:bg-indigo-500` & `text-indigo-600 … dark:text-indigo-400` | **All Primary CTAs** — Sign in, Save Changes, Submit Report, Add Rule, Upload & Import, Add Guest, Mark Contacted, Open dashboard, Get Started. **Every Page eyebrow** (`<p class="text-xs ... text-indigo-600 dark:text-indigo-400">`). Link default. KPI Indigo accent. **`focus:ring-indigo-500`** everywhere. |
| `indigo-700`           | `#4338CA`   | `#A5B4FC`     | `hover:bg-indigo-700`, `via-blue-700`                                      | Primary hover, Login brand panel gradient stop            |
| `indigo-800`           | `#3730A3`   | —            | `active:bg-indigo-800`                                                     | Sign-in button :active                                     |
| `indigo-900`           | `#312E81`   | same         | `via-indigo-900` (Login bg) — also `dark:bg-indigo-900/20`                | Login brand panel base, dark hover wash on QuickSubmit rows |
| `indigo-900/30`        | —           | transparent   | `dark:bg-indigo-900/30`                                                    | EmptyState icon container dark, form-shell error bg dark  |
| `indigo-900/40`        | —           | transparent   | `dark:bg-indigo-900/40`                                                    | Section-header icon-tile dark, KPI hover badge dark, Mark-Contacted chip dark |
| `indigo-950/40`        | —           | transparent   | `dark:from-indigo-950/40`                                                  | Hero band dark (on `from-indigo-50 ... to-blue-50`)        |

**The "hero gradient band" is the only place where a blue→indigo gradient appears in non-Login surfaces.**
- Light: `bg-gradient-to-br from-indigo-50 via-white to-blue-50`
- Dark: `dark:from-indigo-950/40 dark:via-gray-900 dark:to-blue-950/40`
- Used in: `Pages/ImpactCells/Show.tsx`, `Pages/ImpactSubmissions/Show.tsx`.

---

## 5. Status pills (`Components/StatusPill.tsx`)

Every status/role pill in the app goes through `StatusPill`. **Don't roll a new chip** — pick one of these six tones.

| `tone=…`     | Light (`bg / text`)              | Dark (`dark:bg / dark:text`)            | Dot (always same in both modes) |
| ------------ | -------------------------------- | ---------------------------------------- | ------------------------------- |
| `neutral`    | `gray-100 / gray-700`            | `gray-700 / gray-200`                    | `bg-gray-500`                   |
| **`success`**  | `emerald-100 / emerald-700`       | `emerald-900/40 / emerald-300`           | `bg-emerald-500`                |
| **`warn`**     | `amber-100 / amber-700`           | `amber-900/40 / amber-300`               | `bg-amber-500`                  |
| **`danger`**   | `rose-100 / rose-700`             | `rose-900/40 / rose-300`                 | `bg-rose-500`                   |
| **`brand`**    | `red-100 / red-700`               | `red-900/40 / red-300`                   | `bg-red-500`                    |
| **`info`**     | `blue-100 / blue-700`             | `blue-900/40 / blue-300`                 | `bg-blue-500`                   |

**Status semantic map** (which tone matches which business state — do not invent):

| Business value                          | Tone     |
| --------------------------------------- | -------- |
| Visited / Contacted / Mark Contacted / Enabled notification rule / CSV result Created | `success` |
| Not Contacted / Pending Visit / Pending Contacted / CSV result Skipped              | `warn`    |
| Wrong Number / Not Reachable / CSV result Errors / Remove-row                       | `danger`  |
| **Primary** Impact Cell / Administrator (role badge) / ContactsTimeline step circle / **`AvailableForVisit`** status on `Guests/Show` | `brand`   |
| Sub-cell / Member submission type (per `ImpactSubmissions/{Index,MyReports}`) / **`Contacted`** status on `Guests/Show` | `info`    |
| Everything else (any string fallback, Audit subject)                                | `neutral` |

Optional props: `dot` (default `false`), `size: 'sm' | 'md'` (default `'md'`), `className` override.

---

## 6. Secondary accents + decorative (one-offs per page)

### 6a. Login-only decoration (`Layouts/GuestLayout.tsx` + `Pages/Auth/Login.tsx`)

| Element                          | Light                                                                              | Dark   |
| -------------------------------- | ---------------------------------------------------------------------------------- | ------ |
| Brand panel background           | `bg-gradient-to-br from-indigo-600 via-blue-700 to-indigo-900` (solid gradient)     | same   |
| Glow orb 1                       | `bg-white/10 blur-3xl animate-[pulse_8s_ease-in-out_infinite]`                       | same   |
| Glow orb 2                       | `bg-blue-400/25 blur-3xl animate-[pulse_10s_ease-in-out_infinite]`                 | same   |
| Glow orb 3                       | `bg-indigo-400/15 blur-3xl animate-[pulse_12s_ease-in-out_infinite]`                | same   |
| SVG grid overlay                 | `text-white opacity-[0.07]`                                                        | same   |
| Right-edge rail                  | `bg-gradient-to-b from-transparent via-white/30 to-transparent`                     | same   |
| Hero text gradient               | `bg-gradient-to-r from-white via-blue-100 to-indigo-200 bg-clip-text text-transparent` | same |
| Mission-hub indicator dot        | `bg-emerald-300`                                                                   | same   |
| Session status banner            | `border-green-200 bg-green-50 text-green-700`                                       | `dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-300` |

### 6b. Welcome-only decoration (`Pages/Welcome.tsx`)

| Element                          | Light                                                                              | Dark                                          |
| -------------------------------- | ---------------------------------------------------------------------------------- | --------------------------------------------- |
| Glow orb 1                       | `bg-indigo-300/30 blur-3xl animate-[glow_14s_ease-in-out_infinite]`                 | `dark:bg-indigo-500/20`                       |
| Glow orb 2                       | `bg-violet-300/30 blur-3xl animate-[glow_18s_ease-in-out_infinite_2s]`             | `dark:bg-violet-500/15`                       |
| SVG grid overlay                 | `opacity-[0.04]`                                                                   | `opacity-[0.06]`                              |
| Hero text gradient               | `bg-gradient-to-r from-indigo-600 via-violet-600 to-indigo-600 bg-clip-text text-transparent` | `dark:from-indigo-400 dark:via-violet-400 dark:to-indigo-400` |
| Feature-card rings               | `ring-indigo-200`, `ring-violet-200`, `ring-emerald-200`, `ring-amber-200`          | `dark:ring-{color}-500/30` (same four)        |
| Feature-icon tints               | `bg-indigo-50 text-indigo-600` (and equivalents for violet/emerald/amber)           | `dark:bg-{color}-500/10 dark:text-{color}-400`|
| Trust-strip check icon           | `text-emerald-500`                                                                 | same                                          |

### 6c. Status banner palette (Login session status + Edit-form nothingEditable warning)

| State                       | Light                                                       | Dark                                                          |
| --------------------------- | ----------------------------------------------------------- | ------------------------------------------------------------- |
| **Success / status text**   | `border-green-200 bg-green-50 text-green-700`               | `dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-300` |
| **Read-only / warning**     | `border-amber-300 bg-amber-50 text-amber-800`               | `dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200` |
| **Error** (inline banner)   | `bg-red-50 text-red-700` (e.g. submission-form `serverError`) | `dark:bg-red-900/30 dark:text-red-300` |
| **Error** (per-field text)  | `text-sm text-red-600` (e.g. `InputError`, `FormField.error`) | `dark:text-red-400` |

### 6d. Component-local patterns (one per component)

These four patterns are unique enough to deserve their own row — copy the full pair when reusing the component.

| Component / pattern                                                  | Light                                                                                                                                | Dark                                                                                                                       |
| -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------- |
| **ContactsTimeline rail** (`Components/ContactsTimeline.tsx`)        | `before:bg-gray-300`                                                                                                                 | `dark:before:bg-gray-600`                                                                                                  |
| **ContactsTimeline step circle**                                     | `bg-red-100 text-red-700 ring-2 ring-white`                                                                                          | `dark:bg-red-900/50 dark:text-red-300 dark:ring-gray-800`                                                                  |
| **LeadershipBoard tile hover ring** (`Components/LeadershipBoard.tsx`) | `hover:ring-1 hover:ring-red-300`                                                                                                  | `dark:hover:ring-red-500`                                                                                                  |
| **Quick-submit / quick-link dashed card** (Dashboard `LeaderDashboard` + `AdminDashboard`) | `border-2 border-dashed border-gray-300 bg-white hover:border-indigo-400 hover:bg-indigo-50/50 hover:text-indigo-700` | `dark:border-gray-600 dark:bg-gray-800 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/20 dark:hover:text-indigo-300` |
| **Mark Contacted mini-chip** (Dashboard `TeamDashboard` queue row)   | `inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100`        | `dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-900/60`                                                   |

---

## 7. KPI accent map (`Components/KPICard.tsx`)

The dashboard renders five KPI variants. The `accent` prop selects the number colour. **Use these assignments — don't invent new accents.**

| `accent` prop  | Light (`text-{accent}-600`) | Dark (`dark:text-{accent}-400`) | Example placement                                    |
| -------------- | --------------------------- | ------------------------------- | ---------------------------------------------------- |
| `default`      | `text-gray-900`             | `dark:text-gray-100`            | Officer *Response Rate*, Leader *Total*, Sub-cells   |
| `indigo`       | `text-indigo-600`           | `dark:text-indigo-400`          | *Pending Contacts* in Officer/Team/L/Zonal/Admin      |
| `emerald`      | `text-emerald-600`          | `dark:text-emerald-400`         | *Total Calls / Visited / Members / Contacted Guests*  |
| `amber`        | `text-amber-600`            | `dark:text-amber-400`           | *Pending Visit / Pending Contacts* warnings          |
| `rose`         | `text-rose-600`             | `dark:text-rose-400`            | Team *Wrong Number* only                              |
| `blue`         | `text-blue-600`             | `dark:text-blue-400`            | Admin *Impact Cells* only                              |

Optional `delta: { value: number; positiveIsGood?: boolean }` indicator — internally resolves to:
- positive direction matches `positiveIsGood` (default `true`) → `emerald-600/dark:emerald-400`
- otherwise → `rose-600/dark:rose-400`, with `▲`/`▼` glyph and `Math.abs(value).toFixed(1)%`.

---

## 8. Role badges (`Components/RoleBadge.tsx`)

| Spatie role              | Light pill                     | Dark pill                     |
| ------------------------ | ------------------------------ | ----------------------------- |
| `Administrator`          | `red-100 / red-800`            | `red-900 / red-200`           |
| `Supervisor`             | `purple-100 / purple-800`      | `purple-900 / purple-200`     |
| `FollowUpOfficer`        | `blue-100 / blue-800`          | `blue-900 / blue-200`         |
| `Follow_UP`              | `blue-100 / blue-800`          | `blue-900 / blue-200`         |
| `Follow_UP_Admin`        | `indigo-100 / indigo-800`      | `indigo-900 / indigo-200`     |
| `Follow_UP_View_Only`    | `sky-100 / sky-800`            | `sky-900 / sky-200`           |
| `Impact_Leaders`         | `green-100 / green-800`        | `green-900 / green-200`       |
| `Impact_Cell_Admin`      | `emerald-100 / emerald-800`    | `emerald-900 / emerald-200`   |
| `Impact_Cell_Report`     | `teal-100 / teal-800`          | `teal-900 / teal-200`         |
| (unknown / `null` role)  | `gray-100 / gray-600`          | `gray-700 / gray-300`         |

> `RoleBadge` is the **only** place these colour-pairs exist. If you need a role indicator, use `<RoleBadge role={user.activeRole} />`.

---

## 9. Form atoms + inputs

| Component                          | Light                                          | Dark                                                                                  |
| ---------------------------------- | ---------------------------------------------- | ------------------------------------------------------------------------------------- |
| `TextInput`, `<select>`, `<textarea>` border | `border-gray-300`                       | `dark:border-gray-700`                                                                |
| Input surface                      | `bg-white`                                     | `dark:bg-gray-900`                                                                    |
| Input text                         | (inherits)                                     | `dark:text-gray-300`                                                                  |
| **Focus state**                    | `focus:border-indigo-500 focus:ring-indigo-500` | `dark:focus:border-indigo-600 dark:focus:ring-indigo-600`                              |
| Checkbox                           | `border-gray-300 text-indigo-600 ring-indigo-200/50` | `dark:border-gray-700 dark:bg-gray-900`                                       |
| KB order surface (Login ↵ hint)    | `border-gray-200 bg-white text-gray-600 shadow-sm` | `dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400`                          |
| Dropdown trigger button            | `border-gray-200/80 bg-white/80 text-gray-700 shadow-[0_4px_20px_rgba(0,0,0,0.03)]` | `dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-200` |
| Sticky save bar (Guest Edit, Submissions Create) | `border-gray-200 bg-white/90 backdrop-blur-md` | `dark:border-gray-700 dark:bg-gray-800/90`                                            |

The **error helper-text** underneath every input uses: `text-sm text-red-600 dark:text-red-400` (`Pages/Guests/Edit.tsx` + `Pages/ImpactSubmissions/Create.tsx`).

---

## 10. Motion + cursor tokens (every page)

| Class                                          | Effect                                                  |
| ---------------------------------------------- | ------------------------------------------------------- |
| `transition-all duration-200`                  | Default hover transition                                |
| `hover:-translate-y-0.5`                       | Lift on KPI / ImpactCell tile / QuickLink / EmptyState  |
| **`motion-safe:animate-[fadeIn_0.4s_ease-out]`** | Page + section entrance (declared once in each layout)|
| `animate-[pulse_{8,10,12}s_ease-in-out_infinite]` | Login brand-panel glow orbs (staggered durations)     |
| `animate-[glow_{14,18}s_ease-in-out_infinite]` | Welcome glow orbs (`@keyframes glow` declared once on the page) |
| `animate-spin`                                 | Loading spinners across forms and CSV upload           |
| **`focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2`** | Universal focus state (`dark:focus-visible:ring-offset-gray-900` only on Welcome) |
| `data-testid="…" `                              | Required on every interactive/key element (for verifier scripts) |

**Rule:** never block UI via animation — every motion token is `motion-safe:`-guarded, so users with `prefers-reduced-motion: reduce` see an instant transition.

---

## 11. Known gaps (open issues, not yet closed)

| Item                                                                                                | Severity | Files affected                                      |
| --------------------------------------------------------------------------------------------------- | -------- | --------------------------------------------------- |
| **Reports chart fills are hard-coded hex** (`#dc2626`, `#f97316`, `#8b5cf6`, `#fecaca`) rather than token-aware utilities → dark-mode contrast is not audited.   | medium   | `Pages/Reports/Index.tsx`                           |
| Page-level `<style>{@keyframes fadeIn…}</style>` is duplicated in both layouts instead of centralised in `resources/css/app.css`.                                  | low      | `Layouts/AuthenticatedLayout.tsx`, `Layouts/GuestLayout.tsx` |
| `Welcome.tsx` `dark:focus-visible:ring-offset-gray-900` is the **only** place the focus-offset diverges from the rest of the app — consider promoting to § 10 default. | low      | `Pages/Welcome.tsx`                                 |

Open a follow-up issue with one of the labels above if you pick any of these up.

---

## 12. How to add a new component (checklist)

1. **Pick a status tone** from § 5 — do not invent a new colour.
2. **Surface** the component on `bg-white dark:bg-gray-800` (§ 3) unless it must sit on a page gradient (then `bg-white/80 dark:bg-gray-800/80`).
3. **Border** = `border-gray-200 dark:border-gray-700` (§ 3).
4. **Hover** = `hover:bg-indigo-50/40 dark:hover:bg-gray-700/40` if it's a clickable row, otherwise `hover:-translate-y-0.5 hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)]`.
5. **Focus** = `focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2`.
6. **Entrance animation** = wrap the root with `motion-safe:animate-[fadeIn_0.4s_ease-out]`.
7. **`data-testid="…"`** on every interactive/key element in kebab-case.
8. **Add to § 1 source-of-truth row** at the top of this doc so future contributors know where to look.

That's the whole system.

---

## 13. Cross-references

- **Design intent (why we picked these tokens):** [`Implementation/06_Dashboard_Design_System.md`](../../Implementation/06_Dashboard_Design_System.md)
- **Phase 06b → 06c rollout plan:** [`Implementation/Phase_06b-06c_UI_Polish.md`](../../Implementation/Phase_06b-06c_UI_Polish.md)
- **Build state + how to run verifiers:** [`HANDOFF.md`](../../HANDOFF.md)

---

*Last verified against the JSX on: 2026-07-27 (post Phase 06c polish). If you change a component and forget to update the matching table here, future contributors will revert your work by accident — so update § 3–§ 10 in the same PR.*
