# 06 — Dashboard Design System (professional & beautiful)

> Goal: A **polished, modern, trustworthy** admin experience — something that feels as good as **Linear**, **Vercel**, or **Stripe** while keeping SBC's red/white brand.

## Brand & palette

| Token | Light | Dark |
|---|---|---|
| `--brand-red` | `#E53935` | `#E53935` (same — used as the accent) |
| `--brand-red-soft` | `rgba(229,57,53,0.10)` | `rgba(229,57,53,0.18)` |
| `--bg-base` | `#FAFAFA` | `#000000` |
| `--bg-surface` | `#FFFFFF` | `#111111` |
| `--bg-sidebar` | `#FFFFFF` | `#0A0A0A` |
| `--border` | `#E5E5E5` | `#27272A` |
| `--text-primary` | `#171717` | `#EDEDED` |
| `--text-secondary` | `#737373` | `#A1A1AA` |
| `--text-muted` | `#A3A3A3` | `#71717A` |

> Avoid using brand-red on large surfaces. Use it on: primary buttons, active sidebar item, KPI numbers when emphasising a critical metric.

## Typography

- **Font:** `Geist` (Vercel) or `Inter`. Load via `@fontsource-variable/geist` or `@fontsource-variable/inter`.
- **Scale (Tailwind):**
  - Display: `text-4xl font-semibold tracking-[-0.02em]`
  - KPI number: `text-3xl font-semibold`
  - Heading: `text-xl font-semibold tracking-[-0.01em]`
  - Body: `text-sm font-normal leading-6`
  - Caption: `text-xs font-medium tracking-[0.05em] uppercase text-text-secondary`
- **Line heights:** tight for numerics (`leading-tight`), normal for body (`leading-6`), generous for descriptions (`leading-7`).

## Layout primitives

### Sidebar (`<AppLayout />`)

- Fixed left rail, `w-[260px]` expanded, `w-[72px]` collapsed. Persist in `localStorage`.
- Logo block at top: 40x40 red square with white SBC mark.
- Three nav groups (role-dependent):
  1. Main (Dashboard, Guests, Reports, Profile)
  2. **User Group-specific** (one of: Follow UP Officer nav, Follow UP Team nav, Impact Cell Leader nav)
  3. Admin (Users, Impact Cells, Notifications, Settings, Audit) — only for Admins
- **Active state**: 3px left red border + tiny red dot when there are unread items; never a solid red block, just a subtle red-soft background.
- **Hover state**: 5% white overlay in dark mode, 3% black overlay in light mode.
- **Footer**: profile chip (initials avatar + name + role dropdown) and sign-out.

### Header

- `h-16` sticky bar, transparent over the content. On dark mode: faint inner-glow. On light mode: `1px solid --border` at the bottom.
- Search box (placeholder, global filter), theme toggle (animated), user profile, role switcher dropdown (when multi-role).

### Main content

- Max width `1440px`, side padding `24px` desktop, `16px` tablet/mobile.
- Vertical rhythm: `24px` between major sections, `12px` between related cards.

## KPI card

```
┌──────────────────────────────┐
│  TOTAL MEMBERS               │   ← caption (uppercase, 11px, muted)
│  42                          │   ← big number (32px, semibold)
│  ↑ 8%  vs last month         │   ← trend line (green or red)
└──────────────────────────────┘
```

- Radius `12px` (`rounded-xl`).
- 1px border matching the theme; soft shadow on light mode (`0 4px 20px rgba(0,0,0,0.03)`), none on dark mode.
- On dark mode, an inner 1px ring at 8% white for depth.
- **No heavy gradients** — the brand-red gradient is reserved for the **active nav item** and the **primary CTA button** only.

### Primary CTA

- Pill or rounded-md red gradient (`bg-gradient-to-br from-brand-red to-[#C62828]`).
- White text, `font-semibold`.
- Hover: brighten ~6%, slight scale `1.01`, transition `150ms`.
- Active: small inset shadow.
- Disabled: `bg-muted`, `text-text-muted`.

## Chart style (Recharts)

- **AreaChart**: linear gradient `from rgba(229,57,53,0.35)` → `to rgba(229,57,53,0)`. No grid lines (or 6% opacity).
- **BarChart**: rounded top corners (`radius={[6,6,0,0]}`), bar colour = `--text-primary` for neutral bars, `--brand-red` for highlights. Single subtle horizontal axis line.
- **Donut/Pie**: stroke `--bg-surface` 3px between slices; subtle shadows.
- **Tooltip**: surface card with 1px border, 8px radius. Title in caption style, value in big number style.

## Tables

- Generous padding (`px-4 py-3`).
- Header row: caption-style text (`text-xs uppercase`).
- Row hover: 5% white on dark, 3% black on light.
- Selected state: red-soft background.
- Status pills (`Pending`, `Submitted`, etc.): `bg-bg-soft-2`, `text-text-secondary`, `rounded-full px-2 py-0.5 text-[11px] font-medium`.
- No zebra striping.

## Avatars

- Square (32px) with subtle gradient red-to-amber.
- Initials (first letter of first 2 name parts).
- Optional upload image fallback.

## Empty states

- Centered icon (24px, muted), small heading (text-base), one-line description, **one** primary action button.

## Loading states

- Card skeletons (shimmer on dark, soft pulse on light).
- Always: avoid layout shift.

## Microinteractions

- Sidebar nav: 150ms colour transitions, 250ms width transition when collapsing.
- Card hover: subtle lift (`translate-y-[-1px]` + extra shadow on light).
- Buttons: 120ms ease-out on all states.

## The Leadership Board

(See [05_Leadership_Board.md](./05_Leadership_Board.md) for layout.) Tile design:

- Square-ish `aspect-[1/1]` on desktop, full-width on mobile.
- Border-1, radius-12.
- Inside:
  - Title (text-base, semibold) + order pill (`#1`, `#2`, `#3`).
  - 4 mini KPIs with tiny icons and values.
  - Footer with leader name + phone (clickable `tel:` link).
  - Soft red shadow on hover for "Primary → drill" affordance.

## Page patterns we lean on

- **Linear**: tight spacing, monochrome with one accent, sharp typography, dense information without clutter.
- **Vercel**: pure black background, no shadows, generous padding, almost flat.
- **Stripe**: structured data tables, clear hierarchy, subtle gradients in highlights.
- **Notion**: empty states that just teach the user what to do.

## Tailwind v4 tokens

Add to `src/styles.css`:

```css
@theme {
  --color-brand-red: #E53935;
  --color-brand-red-soft: rgba(229, 57, 53, 0.10);
  --color-bg-base: #FAFAFA;
  --color-bg-surface: #FFFFFF;
  --color-bg-sidebar: #FFFFFF;
  --color-border-base: #E5E5E5;
  --color-text-primary: #171717;
  --color-text-secondary: #737373;

  --font-sans: "Geist", "Inter", system-ui, sans-serif;
  --shadow-card: 0 4px 20px rgba(0, 0, 0, 0.03);
}

@media (prefers-color-scheme: dark) {
  :root { color-scheme: dark; }
}
.dark {
  --color-bg-base: #000000;
  --color-bg-surface: #111111;
  --color-bg-sidebar: #0A0A0A;
  --color-border-base: #27272A;
  --color-text-primary: #EDEDED;
  --color-text-secondary: #A1A1AA;
  --shadow-card: none;
}
.dark [data-card] { box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06); }
```

Dark mode toggle: a button in the header. Persist in `localStorage` (`cgms.theme`). On mount, read localStorage or `prefers-color-scheme`.

## Accessibility checklist

- All interactive elements have `aria-label` or visible text.
- Focus rings visible (`focus-visible:ring-2 ring-brand-red/50 ring-offset-2 ring-offset-bg-base`).
- Tab order matches visual order.
- Colour contrast ≥ WCAG AA on both themes.
- Reduced motion: respect `prefers-reduced-motion: reduce` on transitions.

---
*Next: [Phase_01_Foundation.md](./Phase_01_Foundation.md).*
