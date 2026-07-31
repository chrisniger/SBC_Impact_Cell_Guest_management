# Minimax AI Prompt: Premium Dashboard Redesign for Impact Portal

Redesign the dashboard experience for the Impact Portal to look premium, polished, and professional while preserving the existing product structure, routes, data, and behavior.

This is a Laravel + Inertia + React + TypeScript app using Tailwind CSS. The main dashboard file is `resources/js/Pages/Dashboard.tsx`, and all role-based dashboard variants render through the shared `AdminDashboardLayout`.

## Goal

Improve the visual quality of every dashboard without changing the underlying app logic.

The result should feel like a modern operations dashboard for a church impact, guest follow-up, leadership, and reporting system: clean, trustworthy, executive-ready, calm, and highly usable.

## Dashboards to Improve

Update the design treatment for these dashboard variants:

- Administrator dashboard
- Follow-Up Officer dashboard
- Follow-Up Team dashboard
- Impact Cell Leader dashboard
- Impact Cell Administrator dashboard
- Zonal Coordinator dashboard

## Important Constraints

Do not redesign the app from scratch.

Preserve:

- Existing routes
- Existing props and data contracts
- Existing role-based dashboard logic
- Existing forms, links, buttons, tables, filters, and inline update behavior
- Existing dark mode support
- Existing responsive behavior
- Existing `data-testid` attributes
- Existing accessibility behavior in the sidebar and layout
- The current Laravel/Inertia architecture

You may improve layout, spacing, hierarchy, typography, card styling, table styling, status pills, empty states, headers, KPI cards, quick action cards, charts/analytics presentation, and dashboard section composition.

## Visual Direction

Make the dashboards look premium and professional with:

- Stronger spacing rhythm
- Better visual hierarchy
- Cleaner dashboard section headers
- More elegant KPI cards
- More polished tables
- More refined empty states
- Better hover/focus states
- Better mobile/tablet responsiveness
- Better use of white space
- A calm professional color system
- Subtle shadows, borders, and backgrounds
- Clear distinction between metrics, actions, feeds, and operational tables

Avoid a marketing landing-page style. This is an internal operational dashboard, so it should feel focused, efficient, and easy to scan.

Avoid excessive gradients, oversized hero sections, decorative blobs, noisy colors, or anything that makes the dashboard look playful instead of professional.

## Dashboard-Specific Guidance

For the Administrator dashboard, make the KPI overview, quick actions, overview analytics, leadership rollup, system overview, recent activity, and recent registrations feel like a polished executive command center.

For Follow-Up Officer, make “Personal KPIs” and “My Queue” feel action-oriented, with the table designed for fast outreach work.

For Follow-Up Team, make team performance and queue management feel clear, structured, and operational, especially the inline follow-up status controls.

For Impact Cell Leader, improve the leadership tree area, cell snapshot, quick submit actions, recent submissions, and assigned guest workflow so it feels clean and ministry-focused.

For Impact Cell Administrator, make the supervisor snapshot, leadership rollup, and cross-group activity feeds feel high-level and audit-friendly.

For Zonal Coordinator, make the zone snapshot, impact cells grid, and recent submissions feel organized for oversight and drill-down.

## Implementation Expectations

Use Tailwind CSS and existing React components where possible.

Prefer improving reusable components such as KPI cards, page cards, section headers, tables, quick action cards, status pills, and dashboard layout styling instead of duplicating styles across every dashboard.

Keep the design consistent across all roles while allowing each dashboard to emphasize its own workflow.

Make sure the interface works well on desktop, tablet, and mobile.

Do not remove any existing functionality.

Do not change backend controllers, database logic, permissions, or routes unless absolutely required for the visual redesign.

## Acceptance Criteria

The redesigned dashboards should:

- Look premium, modern, and professional
- Keep all existing dashboard functionality working
- Preserve role-specific dashboard content
- Preserve dark mode
- Preserve responsiveness
- Preserve tests and `data-testid` hooks
- Improve readability and scanability
- Avoid visual clutter
- Avoid a full product redesign
- Feel consistent across all dashboard variants

Before finishing, review the dashboard in the browser at `http://127.0.0.1:8000/dashboard` using available roles or dashboard states, and fix any spacing, overflow, contrast, or mobile layout issues.