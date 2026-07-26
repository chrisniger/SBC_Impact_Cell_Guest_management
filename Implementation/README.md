# SBC Guest Management — Implementation Plan

> **Version:** 1.0 — Initial redesign  
> **Production URL target:** `https://app.summitdata.one`  
> **Database:** MySQL/MariaDB on Hostinger (`impact_guest`)

This folder contains the complete phase-by-phase implementation plan for the redesign of the SBC Guest Management System. The new system is built around **three explicit user groups** (Impact Cell, Follow-Up Officer, Follow-Up Teams) who all interact with **Guest** records but with **column-level access control**. It also introduces an **Impact Cell hierarchy** with a **Leadership Board** dashboard.

Files here are written to be:
- **Read-first** before each phase of work begins.
- **Self-contained**, so any AI agent or developer joining mid-project can read a single file and understand what the phase is, what files it touches, what is in scope, and how to verify it.
- **Testable**, with explicit deliverable checklists at the end of each phase.

---

## 🧭 Read in this order

### Part A — Architecture & Design (read before Phase 01)

| # | File | What it covers |
|---|------|---------------|
| 00 | [00_Vision.md](./00_Vision.md) | What we are building, who uses it, success criteria. |
| 01 | [01_Architecture.md](./01_Architecture.md) | Tech stack, monorepo layout, environment, project structure, CI hints. |
| 02 | [02_Database_Schema.md](./02_Database_Schema.md) | Full Prisma schema for the redesign (8 tables + new hierarchy fields + JSON sub-cell rollups). |
| 03 | [03_Three_User_Groups.md](./03_Three_User_Groups.md) | The 3 User Group column-access matrix (the most important decision). |
| 04 | [04_Impact_Cell_Hierarchy.md](./04_Impact_Cell_Hierarchy.md) | Parent → sub-cell data model, seeding the 70 existing cells, migration. |
| 05 | [05_Leadership_Board.md](./05_Leadership_Board.md) | The leadership board dashboard design (layout, stats, drill-down). |
| 06 | [06_Dashboard_Design_System.md](./06_Dashboard_Design_System.md) | The "professional & beautiful" dashboard spec — colors, type, KPI cards, charts. |

### Part B — Implementation phases (read at the start of each phase)

| # | File | What we will build | Exit criteria |
|---|------|-------------------|---------------|
| 01 | [Phase_01_Foundation.md](./Phase_01_Foundation.md) | Project scaffold, install deps, `.env` wiring, Prisma init, prime seed. | App boots, `/api/health` returns `{ok: true}`, `prisma migrate deploy` ran. |
| 02 | [Phase_02_Auth_And_Users.md](./Phase_02_Auth_And_Users.md) | Login, JWT, role middleware, user CRUD, password reset, RBAC. | Admin can log in, create a Follow Up Officer, log in as that user. |
| 03 | [Phase_03_Impact_Cell_Model.md](./Phase_03_Impact_Cell_Model.md) | `parentCellId` / `isPrimary` schema, seed 70 cells, sub-cell CRUD. | An Admin can split "GAMES VILLAGE 1" into 2 sub-cells and view the hierarchy. |
| 04 | [Phase_04_Guest_Records_Core.md](./Phase_04_Guest_Records_Core.md) | Guest CRUD, column-level authorization, sanitize, search, filter. | All 3 user groups can see Guests scoped correctly; sanitization rejects bad data. |
| 05 | [Phase_05_Follow_Up_Officer.md](./Phase_05_Follow_Up_Officer.md) | Assigned-only view, contact-status flow, visitation flow, comments. | Officer can claim a guest, mark "Available for Visit", schedule. |
| 06 | [Phase_06_Follow_Up_Team.md](./Phase_06_Follow_Up_Team.md) | Team dashboard, priority sort, inline status, contact sections. | Follow-Up team sees the queue sorted `NOT CONTACTED → CONTACTED → other`. |
| 07 | [Phase_07_Impact_Cell_Leader.md](./Phase_07_Impact_Cell_Leader.md) | Members Data, Submit Report, Childbirth Notice, Souls Registration, Soul Search, My Reports. | An Impact Leader can submit a weekly report with duplicate prevention. |
| 08 | [Phase_08_Leadership_Board_UI.md](./Phase_08_Leadership_Board_UI.md) | The "leadership board" component, KPI rollups, drill-down side panel. | A Primary Cell leader sees their sub-cells with members/souls/reports counts. |
| 09 | [Phase_09_Notifications_SMTP.md](./Phase_09_Notifications_SMTP.md) | SMTP singleton settings, notification rule CRUD, test email, dispatch on guest assignment. | Reassigning a guest to an Impact Leader sends an email (when SMTP configured). |
| 10 | [Phase_10_CSV_Import_Export.md](./Phase_10_CSV_Import_Export.md) | 3 template variants, column aliasing, dup detection by phone, client-side export. | Admin uploads 100-row CSV → 87 created, 13 skipped (duplicates). |
| 11 | [Phase_11_Reports_And_Audit.md](./Phase_11_Reports_And_Audit.md) | Dashboard aggregations, officer performance, audit log table, monthly reports. | Reports screen shows month-over-month KPI curves. |
| 12 | [Phase_12_Deployment.md](./Phase_12_Deployment.md) | Build, env on Hostinger, `.htaccess`, troubleshooting stale dist, restart-loop. | `https://app.summitdata.one/dashboard` renders latest build. |

---

## 🧩 Shared key concepts (keep Referring back)

- **Three user groups** — every UI screen, every API, every Prisma query must answer: *"Which group am I serving? Which columns can they see?"*
- **Column-level access** — `src/lib/access.ts` and `server/lib/access.js` are the two source-of-truth files every check must route through.
- **Impact Cell hierarchy** — every dashboard endpoint must respect `parentCellId` so children roll up into the parent's board.
- **JWT + active role** — multi-role users switch via the `X-Active-Role` header; backend re-resolves `req.user.role` from it.
- **Hostinger Node.js deployment** — build locally, upload `dist/client` + `dist/server` together, restart via `tmp/restart.txt`.

---

## ✅ Definition of "Done" for the full redesign

1. All 3 user groups can log in, see guests scoped to their group, and edit the columns they own.
2. An Admin can split an Impact Cell into sub-cells and the primary cell's dashboard renders the Leadership Board.
3. Beautiful dashboard (per `06_Dashboard_Design_System.md`) renders flawlessly in light + dark mode.
4. CSV import works with the 3 templates.
5. Email notifications fire on guest assignment to an Impact Leader.
6. The app is deployed on `https://app.summitdata.one` matching local dev parity.
