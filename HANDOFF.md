# HANDOFF — SBC Guest Management Portal (Laravel 12 build)

> **Audience:** a developer (or AI assistant) joining this repo mid-build.
> **Read time:** ~10 min to orient, ~30 min to feel competent.
> **Last updated:** end of **Phase 05 + .htaccess security fix (2026-07-26)** — see § 1 for build state.

This is the **build-state + next-steps** document. For the *design intent* (what we're building and why), see `Plan/` and `Implementation/`. For the *technology mapping* (which Laravel package / idiom replaces which old-stack idea), see `Implementation/00_Laravel_Bridge.md`.

---

## 0. TL;DR

We're rebuilding the SBC Guest Management app — a 3-user-group portal (Impact Cell, Follow Up Officer, Follow Up Team) for tracking "guests" through outreach → follow-up → integration — on a fresh **Laravel 12 + Inertia + React 19 + Breeze + Spatie Permission/Activitylog** stack. The MySQL 8.4 DB (`impact_guest` on localhost, user `ipcDBurs22`) is bootstrapped and live. **Phases 02 → 05 are green (58 / 15 / 36 / 22 sub-assertions all passing — see §1 for per-phase detail).** Next up is **Phase 06** (Follow Up Team group).

---

## 1. Build state

| Phase | Doc | Status |
|---|---|---|
| 00 Pivot | — | ✅ Done — pivoted from Node/Prisma to Laravel 12 |
| 01 Foundation | `Phase_01_Foundation.md` | ✅ Done — Laravel 12.64.0 + Breeze + Spatie scaffold, `.env` configured, `storage:link` created, all baseline migrations applied |
| 02 Auth + Users | `Phase_02_Auth_And_Users.md` | ✅ **Done** — 9 roles seeded, `sbcadmin@impact.test` admin user, `RoleHelper` (3-group matrix — keys are now **snake_case** to match the wire format; see post-Phase-05 fix below), `User::activeRole()` accessor, `POST /auth/switch-role` route. **58/58 verification assertions pass** (was 40/40; added `[1b]` matrix-shape regression guard in the post-Phase-05 fix). |
| 02b **UI wire for Phase 02** | — | ✅ **Done** — top-bar role badge + role switcher dropdown (`RoleBadge.tsx`, `RoleSwitcher.tsx`), `HandleInertiaRequests` shares `auth.user.{activeRole,roles,hasMultipleRoles}`, stub pages render without 500. |
| 03 Impact Cell hierarchy | `Phase_03_Impact_Cell_Model.md` | ✅ **Done** — `impact_cells` table (UUID PK, `parent_cell_id` FK self `restrictOnDelete`, `is_primary`, `order`), `ImpactCell` model with `parent()` + `subCells()` self-relations + `hierarchyRulesOrThrow()` validator, `ImpactCellSeeder` (69 cells: 65 primary + 4 APO sub-cells), `ImpactCellController` (CRUD with pre-check + delete + `abort(409)` for destroy), 5 routes. **15/15 sub-assertions pass** (Phase 03 verifier impl has 7 numbered cases; `[2]` (count split) + `[3]` (hierarchy invariant) + `[5]` (controller 409 path) + `[6]` (idempotency) are all multi-check, hence 15 total. No Phase-02-style `[1b]` shape guard was added — coverage gained from earlier split-into-sub-checks refactors). |
| 04 Guest CRUD + column policy | `Phase_04_Guest_Records_Core.md` | ✅ **Done** — `guests` table (UUID PK, `softDeletes`, `nearest_impact_cell_id` FK `restrictOnDelete`, `follow_officer_id` FK `restrictOnDelete`, 7 indexes + `follow_up_contacts` JSON), `Guest` model (`HasUuids` + `SoftDeletes` + `nearestImpactCell()` + `followOfficer()` + `scopeAssignedTo` + `scopeForImpactCell`), `GuestRequest` (`prepareForValidation` strips via `RoleHelper::stripDisallowed` — now correctly strips per snake_case matrix), `GuestResource` (admin-only timestamp masking), `GuestPolicy` (row-level by role+group), `GuestController` (5 routes + cross-cutting rule clearing `visitation_status`/`feedback` when `contacted_status != AvailableForVisit`), `ImpactCellPolicy` (Phase 03 follow-up: admin-only updates). **36/36 sub-assertions pass** (Phase 04 verifier has 20 numbered cases; `[6]`/`[7]`/`[8]` (Form Request stripping per role) + `[12]`/`[13]` (GuestResource masking per role) each split into ~3 sub-checks, hence 36 total. No new `[21]` shape guard in this phase — the snake_case-shape regression guard lives in `verify_phase02_run.php` `[1b]` only). |
| 04b **Local infrastructure** | — | ✅ **Done** — parent `.htaccess` bridges Laragon's auto-vhost (`DocumentRoot=project parent`) into Laravel's `public/` entry. Unconditional rewrite for non-`/public/` paths (so `composer.json`, `app/*`, `routes/*` etc. are never servable). Belt-and-suspenders `RedirectMatch 404 /\.(?!well-known/)` + `Options -Indexes`. |
| 05 Follow Up Officer dashboard + reassign permission | `Phase_05_Follow_Up_Officer.md` | ✅ **Done** — `DashboardController` with 5 KPIs (Pending Contacts / Total Calls / Visited / Pending Visit / Response Rate) for the FUp Officer group, top-8 queue, role-aware top-bar nav (Dashboard / My Guests / Profile when officer; admin keeps full nav), reusable `KPICard` + `StatusPill` components. `RoleHelper::canEditField` extended: `follow_officer_id` is now a Per-Role Special Case — only `Administrator` + `Follow_UP_Admin` may reassign (matrix self-assign otherwise handled in `GuestController::store`). Officer dashboard variant in `Pages/Dashboard.tsx`. **`FollowUpOfficerSeeder`** seeds `officer1@impact.test` (FollowUpOfficer, 5 guests) + `followUpAdmin@impact.test` (Follow_UP_Admin, can reassign). **22/22 sub-assertions pass.** |
| 05b **snake_case matrix fix (silent data-loss bug)** | — | ✅ **Done** — `RoleHelper::GROUP_GUEST_OWNER` matrix changed camelCase → snake_case to match the production HTTP wire format (DB columns, migration columns, GuestResource output, frontend types all use snake_case). Without this fix, every multi-word field was being **silently stripped** from FollowUpOfficer / Follow_UP / Impact_Leaders writes — the strip policy was checking the wrong casing. Cross-cutting: the Inertia-prop boundary in `DashboardController` still camelizes (`'contactedStatus' => $g->contacted_status`) for idiomatic React/TS, but the policy operates on snake_case. Verified by 58/15/36/22 across all 4 phases plus a `[1b]` snake_case-shape regression guard that fails fast if a future contributor types camelCase in the matrix. |
| 06 Follow Up Team dashboard | `Phase_06_Follow_Up_Team.md` | ⏭ Next |
| 07 Impact Cell leader forms | `Phase_07_Impact_Cell_Leader.md` | ⏳ Pending |
| 08 Leadership Board | `Phase_08_Leadership_Board_UI.md` | ⏳ Pending |
| 09 Notifications + SMTP | `Phase_09_Notifications_SMTP.md` | ⏳ Pending |
| 10 CSV import/export | `Phase_10_CSV_Import_Export.md` | ⏳ Pending |
| 11 Reports + Audit | `Phase_11_Reports_And_Audit.md` | ⏳ Pending |
| 12 Deployment | `Phase_12_Deployment.md` | ⏳ Pending |

**Last verified green: Phase 02 (58/58) + Phase 03 (15/15) + Phase 04 (36/36) + Phase 05 (22/22), 2026-07-26** (the only new assertion ADDED in the post-Phase-05 snake_case-matrix commit is the 18-row `[1b]` shape guard in `verify_phase02_run.php`. Phase 03 + Phase 04 grew their counts in earlier refactors that split numbered cases into multiple sub-assertions `[6]/[7]/[8]/[12]/[13]` — not from the snake_case commit).
- `scripts/verify_phase02_run.php` — source of truth for Phase 02 — runs the auth + RBAC + RoleHelper + active-role checks.
- `scripts/verify_phase03_run.php` — source of truth for Phase 03 — runs the impact_cell hierarchy + seeder idempotency checks.
- `scripts/verify_phase04_run.php` — source of truth for Phase 04 — runs the guest FK + Form Request stripping + Resource masking + Policy + ImpactCellPolicy checks.
- `scripts/verify_phase05_run.php` — source of truth for Phase 05 — runs the officer KPI math + role-aware top-bar nav + reassign-permission + seeder idempotency checks.
- If any prints anything other than `N pass / 0 fail`, fix the regression before continuing.

---

## 2. Quick start (5 min)

```bash
cd C:\laragon\www\impact_portal_plus

# 1. Confirm Laravel boots and DB is reachable (current APP_URL is http://impact_portal_plus.test)
php artisan --version                        # → Laravel Framework 12.64.0
php artisan db:show                          # → impact_guest / 15+ tables

# 2a. Run all 4 verification suites
for s in 02 03 04 05; do php scripts/verify_phase0${s}_run.php || { echo "Phase ${s} regressed"; exit 1; }; done
# MUST print "58 pass / 0 fail", "15 pass / 0 fail", "36 pass / 0 fail", "22 pass / 0 fail" (post-Phase-05 snake_case fix added 1 regression guard to each of Phase 02/03/04)

# 3. Browse the app via Laragon's vhost
#    http://impact_portal_plus.test/         (welcome page)
#    http://impact_portal_plus.test/login    (Breeze login — sbcadmin@impact.test //Chris##101)
#                                                          officer1@impact.test   //Officer##101   — FollowUpOfficer
#                                                          followUpAdmin@impact.test //Admin##101  — Follow_UP_Admin
```

If any verifier doesn't pass, **do not proceed** — fix the regression first. See § 8.

---

## 3. Tech stack

| Layer | Choice | Why |
|---|---|---|
| HTTP framework | **Laravel 12** | Per user confirmation; idiomatic PHP web framework |
| Auth | **Breeze (React + TS) + Sanctum stateful** | Inertia pairs naturally with session cookies; no JWT tokens |
| Frontend | **Inertia.js v2 + React 19 + Vite + Tailwind v4** | SPA feel without an API layer; controllers return `Inertia::render('Page', props)`. (Radix + shadcn-style primitives planned for Phase 04+) |
| ORM | **Eloquent** with UUID PKs | Replaces Prisma |
| Roles | **Spatie Laravel-Permission** for 9 roles | DB-backed `roles` table; pivot table auto-managed |
| Groups (3) | **`app/Support/RoleHelper.php`** (custom) | Lives in code, not DB — 3 arrays + a column-access matrix |
| Audit | **Spatie Laravel-Activitylog** | Native Eloquent observer; replaces old custom `AuditLog` table |
| Cache | **`Cache::remember`** with `file` driver | No custom `DashboardCache` table |
| CSV import | **maatwebsite/excel (Laravel Excel)** | Built-in queue/batch/chunk |
| DB | **MySQL 8.4** at `localhost:3306` (`impact_guest`) | Uses `caching_sha2_password`; PHP PDO/mysqlnd handles it natively |

The complete mapping table (Node/Express → Laravel) is in `Implementation/00_Laravel_Bridge.md` § 2.

---

## 4. The 9 roles + 3 groups (glossary)

The **9 Spatie roles** are seeded by `RolesAndPermissionsSeeder`:

```
Administrator       Supervisor         FollowUpOfficer
Follow_UP           Follow_UP_Admin    Follow_UP_View_Only
Impact_Leaders      Impact_Cell_Admin  Impact_Cell_Report
```

They collapse into **3 functional groups** (defined in `RoleHelper::GROUP_*` constants):

| Group key | Members | Owns these Guest columns |
|---|---|---|
| `impactCell` | `Impact_Leaders`, `Impact_Cell_Admin`, `Impact_Cell_Report` | `impact_status`, `nearest_impact_cell_id` |
| `followUpOfficer` | `FollowUpOfficer`, `Follow_UP_Admin` | `gender`, `marital_status`, `age`, `phone`, `address`, `contacted_status`, `join_when`, `days_available`, `comments`, `visited`, `visited_at`, `indicated_to_join`, `visitation_status`, `feedback` |
| `followUpTeam` | `Follow_UP`, `Follow_UP_View_Only` | `follow_up_status`, `follow_up_contacts` |
| (no group) | `Administrator`, `Supervisor` | `Administrator` writes everything; `Supervisor` writes nothing on Guest |

**Note (post-Phase-05 fix):** all matrix KEY names are **snake_case** to match the production HTTP wire format (`$request->all()` returns snake_case, DB columns are snake_case, GuestResource outputs snake_case, frontend types are snake_case). An earlier v1 of this file had camelCase keys and was silently stripping every multi-word field from FollowUpOfficer writes — fixed in the post-Phase-05 commit. A regression guard in `verify_phase02_run.php` / `verify_phase04_run.php` fails fast if a future contributor types camelCase.

Source of truth: `app/Support/RoleHelper.php` (GROUP_GUEST_OWNER matrix) + `Implementation/03_Three_User_Groups.md` (design intent).

---

## 5. File map (where to find what)

```
impact_portal_plus/
├── HANDOFF.md                                  ← you are here
├── README.md                                   ← Laravel default (not project-specific)
│
├── Plan/                                       ← ORIGINAL design intent (do not edit)
│   ├── Functional_Documentation/   (14 docs: system overview, roles, workflows, lifecycle, ...)
│   └── Technical_Documentation/    (21 docs: architecture, schema, API, ...)
│
├── Implementation/                              ← build-phase docs + the bridge
│   ├── 00_Vision.md
│   ├── 01_Architecture.md
│   ├── 02_Database_Schema.md
│   ├── 03_Three_User_Groups.md     ← column-edit matrix source of truth
│   ├── 04_Impact_Cell_Hierarchy.md ← Phase 03 reference
│   ├── 05_Leadership_Board.md
│   ├── 06_Dashboard_Design_System.md
│   ├── Phase_01_Foundation.md
│   ├── Phase_02_Auth_And_Users.md
│   ├── Phase_03..12_*.md           ← future phases
│   └── 00_Laravel_Bridge.md        ← THE tech-mapping doc (read this for Laravel idioms)
│
├── app/
│   ├── Models/User.php                                  ← HasRoles + activeRole() accessor
│   ├── Models/ImpactCell.php                            ← UUID PK, parent() + subCells() self-relations, hierarchyRulesOrThrow()
│   ├── Http/Controllers/Auth/RoleSwitchController.php   ← POST /auth/switch-role
│   ├── Http/Controllers/ImpactCellController.php       ← CRUD with pre-check + delete + abort(409)
│   ├── Http/Middleware/HandleInertiaRequests.php        ← shares auth.user.{activeRole,roles,hasMultipleRoles}
│   ├── Support/RoleHelper.php                           ← 3 groups + matrix + stripDisallowed
│   └── ...  (Breeze Auth/Profile controllers)
│
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php                  (Breeze — includes `sessions` table for SESSION_DRIVER=database)
│   │   ├── 0001_01_01_000001_create_cache_table.php                  (Breeze — cache + cache_locks)
│   │   ├── 0001_01_01_000002_create_jobs_table.php                   (Breeze — jobs + job_batches + failed_jobs)
│   │   ├── 2026_07_26_120000_add_active_role_to_users_table.php      ← Phase 02
│   │   ├── 2026_07_26_130000_create_permission_tables.php            ← Spatie (copied from stub)
│   │   ├── 2026_07_26_130001_create_activity_log_table.php           ← Spatie (copied from stub)
│   │   └── 2026_07_27_120000_create_impact_cells_table.php           ← Phase 03
│   └── seeders/
│       ├── DatabaseSeeder.php                  ← calls the three below
│       ├── RolesAndPermissionsSeeder.php       ← 9 roles
│       ├── AdminUserSeeder.php                 ← sbcadmin@impact.test //Chris##101
│       └── ImpactCellSeeder.php                ← 69 cells: 65 primary + 4 APO sub-cells
│
├── config/permission.php   ← Spatie Permission config (copied from stub)
├── routes/web.php          ← has /auth/switch-role + 5 /impact-cells routes under auth middleware
├── scripts/
│   ├── verify_phase02_run.php    ← 58-sub-assertion Phase 02 verifier (incl. [1b] snake_case-shape guard)
│   ├── verify_phase03_run.php    ← 15-sub-assertion Phase 03 verifier (7 numbered cases, some multi-check)
│   ├── verify_phase04_run.php    ← 36-sub-assertion Phase 04 verifier (20 numbered cases, guests FK + stripping + Resource + Policy)
│   ├── verify_phase05_run.php    ← 22-sub-assertion Phase 05 verifier (officer KPIs + reassign + seeder idempotency)
│   └── probe_password_hash.php   ← confirms the 'hashed' cast fires correctly
├── resources/js/
│   ├── Components/RoleBadge.tsx               ← top-bar role badge (Phase 02 UI wire)
│   ├── Components/RoleSwitcher.tsx            ← dropdown of roles; visible only when hasMultipleRoles
│   ├── Layouts/AuthenticatedLayout.tsx        ← RoleBadge in dropdown trigger + RoleSwitcher in header; both also in mobile menu
│   ├── Pages/ImpactCells/Index.tsx            ← Phase 03 stub — totals + Phase 04 note
│   └── Pages/ImpactCells/Show.tsx             ← Phase 03 stub — cell details + sub-cells list
└── public/  resources/  storage/  tests/   ← standard Laravel + Breeze frontend
```

---

## 6. What's built (Phase 02 + 02b-UI + Phase 03 deliverables)

| Concern | File | What it does |
|---|---|---|
| **Phase 02 — auth + RBAC + active-role** | | |
| `users.active_role` column | `database/migrations/2026_07_26_120000_add_active_role_to_users_table.php` | Nullable string, after `password` |
| User model with `HasRoles` | `app/Models/User.php` | Adds Spatie roles + 3 helpers: `activeRole()`, `canSwitchTo($role)`, `activeGroup()` |
| **Active-role accessor (single source of truth)** | `User::activeRole()` | Resolves `$user->active_role` if it's still in the user's Spatie roles, else first Spatie role, else `null` — handles stale-column fallback defensively. **USE THIS everywhere** instead of inlining `?? $user->getRoleNames()->first()`. |
| 3-group RBAC matrix | `app/Support/RoleHelper.php` | `GROUP_IMPACT_CELL`, `GROUP_FOLLOW_UP_OFFICER`, `GROUP_FOLLOW_UP_TEAM` + `GROUP_GUEST_OWNER` matrix + `groupOf()`, `canEditField()`, `stripDisallowed()`, `allGroupOwnedFields()` |
| Spatie config | `config/permission.php` | Default Spatie config (copied from stub) |
| Roles table + pivot tables | `database/migrations/2026_07_26_130000_create_permission_tables.php` | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (copied from stub) |
| Activity log table | `database/migrations/2026_07_26_130001_create_activity_log_table.php` | Spatie's `activity_log` table (copied from stub) |
| 9 roles seeded | `database/seeders/RolesAndPermissionsSeeder.php` | Calls `forgetCachedPermissions()` before & after per bridge §7 pitfall #7 |
| Admin user | `database/seeders/AdminUserSeeder.php` | `sbcadmin@impact.test` / `//Chris##101` / Administrator / `active_role = Administrator`. **Password is auto-hashed by the `'hashed'` cast in `User::casts()` — never `Hash::make()` here.** |
| Switch-role endpoint | `app/Http/Controllers/Auth/RoleSwitchController.php` + `routes/web.php` | `POST /auth/switch-role` under `auth` middleware. Validates via `User::canSwitchTo()`. `back(303)->with('success', ...)` so Inertia partial-reloads pick up the new active role. |
| `.env` config | `.env` | MySQL: `ipcDBurs22@127.0.0.1:3306/impact_guest` (password `Ldywgw^5676GGH` — `^` is literal). `APP_URL=http://impact-portal.test`. `SESSION_DRIVER=database`. `CACHE_STORE=file`. |
| Storage symlink | `public/storage` → `storage/app/public` | Created via `php artisan storage:link` per bridge §4 step 5 |
| **Phase 02b — UI wire** | | |
| Inertia shared props | `app/Http/Middleware/HandleInertiaRequests.php` | `share()` now adds `auth.user.{activeRole, roles, hasMultipleRoles}` so the frontend reads single-source-of-truth role data via `usePage().props.auth.user.*` |
| Role badge component | `resources/js/Components/RoleBadge.tsx` | Inline pill next to user name in the dropdown trigger; role-specific color; shows "No role" if activeRole is null |
| Role switcher component | `resources/js/Components/RoleSwitcher.tsx` | Dropdown of `user.roles`; visible only when `hasMultipleRoles`; POSTs to `/auth/switch-role` via Inertia's `router.post`; preserves scroll + state |
| Top-bar wiring | `resources/js/Layouts/AuthenticatedLayout.tsx` | Renders `<RoleBadge>` in user dropdown trigger + `<RoleSwitcher>` next to it (desktop). Both also appear in the mobile responsive menu (the menu shows a role list with the active one checked). |
| **Phase 03 — Impact Cell hierarchy** | | |
| `impact_cells` table | `database/migrations/2026_07_27_120000_create_impact_cells_table.php` | UUID PK, `name` unique, `phone`/`address` nullable, `parent_cell_id` (FK self with `restrictOnDelete()`), `is_primary` boolean, `order` int, indexes on `parent_cell_id`, `is_primary`, and `(is_primary, order)` composite |
| ImpactCell model | `app/Models/ImpactCell.php` | UUID PK via `HasUuids`, `parent(): BelongsTo` + `subCells(): HasMany` self-relations, two validators (`hierarchyRules()` returning array + `hierarchyRulesOrThrow()` unified throw), `ensureParentIsPrimary()`, `scopePrimary()` + `scopeOrdered()` |
| ImpactCell seeder | `database/seeders/ImpactCellSeeder.php` | 68 names from `Plan/Technical_Documentation/Appendix.md` + 1 added "APO" so the split demo has a parent → **69 total: 65 primary + 4 APO sub-cells**. Idempotent via `firstOrCreate(['name' => …])`. |
| ImpactCell controller | `app/Http/Controllers/ImpactCellController.php` | `index`/`show`/`store`/`update`/`destroy`. `destroy()` uses `abort(409)` (not try/catch QueryException — a blanket catch would mask future FK violations from `ImpactSubmissions.impact_cell_id` once Phase 04+ ships). |
| ImpactCell routes | `routes/web.php` | 5 routes under `auth` middleware: `GET/POST /impact-cells`, `GET/PUT/DELETE /impact-cells/{id}`. Admin-only check deferred to `ImpactCellPolicy` (Phase 03 follow-up). |
| Inertia stub pages | `resources/js/Pages/ImpactCells/{Index,Show}.tsx` | Phase 03 stubs render totals + cell details. Phase 04 will flesh out the full admin tree view. |
| **Phase 04 — Guest CRUD + column policy** | | |
| `guests` table | `database/migrations/2026_07_27_130000_create_guests_table.php` | UUID PK, every column from `Implementation/02_Database_Schema.md`, `softDeletes()`, `nearest_impact_cell_id` FK nullable `restrictOnDelete`, `follow_officer_id` FK nullable `restrictOnDelete` (`foreignId` = `unsignedBigInteger` for compatibility with `users.id`), `follow_up_contacts` JSON (max 3 sections), 7 indexes including composite `[follow_officer_id, deleted_at]`. |
| Guest model | `app/Models/Guest.php` | UUID PK via `HasUuids` + `SoftDeletes` traits, `nearestImpactCell(): BelongsTo ImpactCell`, `followOfficer(): BelongsTo User`, scopes `scopeAssignedTo($userId)` + `scopeForImpactCell($cellId)`. |
| Guest form request | `app/Http/Requests/GuestRequest.php` | `prepareForValidation()` calls `RoleHelper::stripDisallowed($this->user()?->activeRole(), $this->all())` + `$this->replace(...)`. **Single source of truth**: column-write decisions live in `RoleHelper::GROUP_GUEST_OWNER`. |
| Guest resource | `app/Http/Resources/GuestResource.php` | `deleted_at` + `updated_at` + `created_at` are null for non-admin (per spec "Hidden from non-admins: ... raw timestamps"). All other fields returned as-is. |
| Guest policy | `app/Policies/GuestPolicy.php` | `view` / `create` / `update` / `delete` by role + group. **Officer**: their `follow_officer_id` matched. **ImpactCell**: their `impact_cell_id` matched (column will be added in Phase 07 — silent fallback today). **followUpTeam**: read-only. Admin: full. |
| Guest controller | `app/Http/Controllers/GuestController.php` | `index`/`show`/`store`/`update`/`destroy` returning `Inertia::render('Guests/{Index,Show}', ...)`. **Cross-cutting rule**: if `contacted_status != AvailableForVisit`, `visitation_status` + `feedback` are nulled server-side (business rule). Self-assign on `store()` for non-admin. |
| ImpactCell policy | `app/Policies/ImpactCellPolicy.php` | Phase 03 follow-up: only Administrator can `update`. (Read-only for everyone else.) |
| Inertia stub pages | `resources/js/Pages/Guests/{Index,Show}.tsx` | Phase 04 stubs render the role-aware column set. Phase 05+ will flesh out the per-group dashboards + inline editing. |
| **Phase 04b — Local infrastructure (Laragon bridge)** | | |
| Parent `.htaccess` | `C:\laragon\www\impact_portal_plus\.htaccess` | **Purpose**: Laragon auto-vhost (`DocumentRoot=project parent`) cannot reach Laravel's `public/` entry. This file bridges parent → `public/` so the auth pages load. **Security**: the rewrite is **unconditional** for non-`/public/` paths — `GET /composer.json`, `/HANDOFF.md`, `/app/*`, `/routes/*`, etc. all forward to `public/index.php` → Laravel router → 404. Belt-and-suspenders `RedirectMatch 404 /\.(?!well-known/)` short-circuits dot-files (`/.env`, `/.git/HEAD`) at the URI translation phase so they never reach the rewrite chain. `Options -Indexes` prevents directory listings. **Production**: in production the vhost's `DocumentRoot` is properly set to `public/`, so this file lives outside the served tree and is never read by Apache — no behavior change, zero risk. |
| **Phase 05 — Follow Up Officer dashboard + reassign permission** | | |
| Officer dashboard controller | `app/Http/Controllers/DashboardController.php` | `index()` computes 5 KPIs (`pendingContacts`, `totalCalls`, `visited`, `pendingVisit`, `responseRate`) + top-8 queue when the active role is in `GROUP_FOLLOW_UP_OFFICER`; falls through to the admin welcome otherwise. Inertia page = `Dashboard` with the matching props. Wired in `routes/web.php` (was an inline closure before). |
| RoleHelper reassign exception | `app/Support/RoleHelper.php` | `canEditField()` now special-cases the `follow_officer_id` column: only `Administrator` + `Follow_UP_Admin` may reassign (per `Implementation/03` matrix table). The matrix for the Follow Up Officer group stays unchanged; self-assign in `GuestController::store` overrides any client-provided value for non-admin. Documented in the file docblock. |
| `FollowUpOfficerSeeder` | `database/seeders/FollowUpOfficerSeeder.php` | Seeds two test users so the verifier + manual QA have a reliable state: `officer1@impact.test` (`//Officer##101`, `FollowUpOfficer`, with 5 assigned guests spanning each `contacted_status`) + `followUpAdmin@impact.test` (`//Admin##101`, `Follow_UP_Admin`). Idempotent via `firstOrCreate`. Wired into `DatabaseSeeder`. |
| Reusable components | `resources/js/Components/{KPICard,StatusPill}.tsx` | `KPICard`: 12px radius, caption + big number + trend slot, dark-mode-aware surface + inner ring. `StatusPill`: 11px font with role/group color hints (Pending = neutral, Visited = green, Issue = red). |
| Role-aware top-bar nav | `resources/js/Layouts/AuthenticatedLayout.tsx` | Three nav states: **Admin**: Dashboard, Guests, Impact Cells, Profile. **Follow Up Officer / Team / Impact Leader**: Dashboard, My Guests, Profile (admin links hidden when active role is non-admin). Selection driven by `auth.user.activeGroup` from `HandleInertiaRequests` shared props — single source of truth. |
| Officer variant page | `resources/js/Pages/Dashboard.tsx` | Branches on `auth.user.activeGroup`: officer → 5 KPI cards + top-8 queue (sorted NOT CONTACTED first) + role-aware empty state. Otherwise → admin welcome. |

---

## 7. Verification (must stay green)

```bash
# Phase 02 — 58-sub-assertion regression net — exits non-zero on any regression
#   (includes [1b]: 18-row snake_case-shape guard covering every GROUP_GUEST_OWNER key)
php scripts/verify_phase02_run.php

# Phase 03 — 15-sub-assertion regression net — asserts hierarchy + split + idempotency
php scripts/verify_phase03_run.php

# Phase 04 — 36-sub-assertion regression net — guests FK + prepareForValidation stripping + Resource masking + Policy
php scripts/verify_phase04_run.php

# Phase 05 — 22-sub-assertion regression net — officer KPIs + reassign permission + nav scoping
php scripts/verify_phase05_run.php

# Probe — confirms the 'hashed' cast fires for the seeded admin password
php scripts/probe_password_hash.php
```

**Phase 02 verifier asserts**: 9 roles seeded (with exact names), admin user has Administrator + active_role, `RoleHelper::groupOf` 11-case matrix, `canEditField` 11-case policy, `stripDisallowed` 4-case (Admin pass-through / Officer strip / null + unknown-role defensive), `/auth/switch-role` route registered, `allGroupOwnedFields` ≥ 10 field names.

**Phase 03 verifier asserts**: 69 rows seeded, 65 primary + 4 sub-cells, hierarchy invariant (every non-primary has `parent_cell_id`; no primary has a parent), the APO primary has exactly the 4 expected sub-cells, `subCells()->exists()` is true for APO (controller would 409) and false for a leaf primary, seeder idempotency (count + split re-applied on re-run).

**Phase 04 verifier asserts**: guests table + every required column exists, both FKs use `restrictOnDelete`, `GuestRequest::prepareForValidation` strips per-group (FollowUpOfficer → keeps officer cols / drops impact_status + follow_up_status + admin-only; Follow_UP → keeps team cols / drops phone + impact_status; Impact_Leaders → keeps impact_cell cols / drops phone + follow_up_status; Administrator → pass-through), cross-cutting rule zeroes `visitation_status` + `feedback` when `contacted_status != AvailableForVisit`, `GuestResource` masks `created_at` / `updated_at` / `deleted_at` for non-admin + exposes them for admin, `GuestPolicy::delete` denies non-admin, `GuestPolicy::view` allows admin + assigned officer / denies unassigned officer, `ImpactCellPolicy::update` allows only Administrator, all 5 guest routes + 5 impact-cell routes registered.

**Phase 05 verifier asserts**: 2 officer test users seeded with the right roles + active_role, officer1 has 5 assigned guests across all 5 `contacted_status` permutations, none have `deleted_at`, `RoleHelper::canEditField` Special-Case holds (Administrator = true, Follow_UP_Admin = true, plain FollowUpOfficer = false, Impact / Team groups = false, Supervisor / null / unknown = false), `DashboardController` route registered, KPI math is exact (Pending Contacts + Total Calls + Visited + Pending Visit + Response Rate all match the seeded counts), officer queue size exactly 8 (LIMIT 8) + sorted with NOT CONTACTED first, role-aware top-bar nav exposes `My Guests` link for officer role only (admin / supervisor / no role = no officer nav), no `.env` / `public/hot` / `vendor` / `node_modules` in any verifier intermediate state.

**Run this after every Phase 02 edit** (and after migrations / seeders change). It is the regression net for everything Phase 02 touches.

Bonus password spot-check (proves the `'hashed'` cast fires):
```bash
php scripts/probe_password_hash.php
```
The script reads `sbcadmin@impact.test`'s stored password, asserts it's a real `$2y$` bcrypt hash, runs `Hash::check('//Chris##101', $hash)`, and exits non-zero if the cast ever silently logs plaintext. Use this instead of an inline `php -r "…"` — every PHP `$var` reference has to be escaped as `\$var` to survive bash variable interpolation, and Windows bash (Git Bash) handles the escaping inconsistently with PHP's own quoting rules. A standalone script is portable, copy-pasteable, and exits cleanly with a status code you can grep.

---

## 8. What's next — Phase 06 (Follow Up Team group)

Outlined in `Implementation/Phase_06_Follow_Up_Team.md`. The Team group's dashboard widens the per-officer view into a multi-officer queue with `FollowUpStatus` inline edits and the 3-section `ContactSections` builder (`follow_up_contacts[]` capped at 3 per spec).

Concrete Phase 06 deliverables (planned):
1. **Team dashboard variant** in `Pages/Dashboard.tsx` (or `Pages/Dashboard/Team.tsx`) — new KPIs: Team-wide Pending Contacts / Wrong Number / Not Reachable / Contacted Today.
2. **Inline status dropdown** on `/guests` for `Follow_UP` + `Follow_UP_Admin` — saves via `router.put` on the existing `GuestController::update` (sanitizer already strips non-team fields per Phase 04).
3. **`<ContactsTimeline>` component** visualizing `follow_up_contacts[]` (max 3 sections per matrix).
4. **`Follow_UP_View_Only` banner** + disabled controls.
5. **Seeding**: 1 `Follow_UP` test user + 3 guests with `follow_up_contacts[3]` populated; **6 verifier assertions** (team role nav + Read-only banner + ContactsTimeline mounts + sanitiser drops phone when team posts it).

---

## 9. Common gotchas (quickref)

The full list is in `Implementation/00_Laravel_Bridge.md` § 7. The three that bit us during Phase 02:

1. **Windows/Laragon — `composer require` dies with `Access is denied (code: 5)` on `bootstrap\cache\services.php`.**
   Root cause: **Laragon's Watch file-monitor service** in the tray menu scanning `bootstrap/cache/` mid-rename. Toggle Watch OFF in the tray, re-run, then re-enable. Manual fallback: `rm -f bootstrap/cache/*.tmp bootstrap/cache/services.php bootstrap/cache/packages.php` + re-run with `--no-scripts`.

2. **Spatie migrations are `.stub` files — NOT auto-discovered by Laravel 12.** `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"` returns "No publishable resources" in Spatie v8.x. You must `cp` the stub files into `database/migrations/` manually with a timestamp prefix (see Phase 02 § 6 above for the exact paths).

3. **`User::activeRole()` cache staleness** — if an admin strips a Spatie role without calling `forgetCachedPermissions()`, the accessor falls back to the first Spatie role (correct behavior, but the stale-column value is silently ignored). Mitigation: `RolesAndPermissionsSeeder::run()` calls `forgetCachedPermissions()` before AND after seeding.
4. **Cross-platform paths / `Storage::disk('public')`.** All file storage MUST go through Laravel's `Storage` facade (`Storage::disk('public')`). Hardcoded `C:\\…` paths will break on the Linux deploy. Per bridge §7 pitfall #3.

---

## 10. References

- **Tech mapping** — `Implementation/00_Laravel_Bridge.md` (the Rosetta Stone between v2 design and Laravel 12 idioms)
- **Design intent (system overview, roles, workflows, lifecycle)** — `Plan/Functional_Documentation/` (14 files: `00_Table_of_Contents.md` through `Appendix.md`)
- **Design intent (architecture, schema, API)** — `Plan/Technical_Documentation/` (21 files: `00_Table_of_Contents.md` through `Appendix.md`)
- **Build-phase specs** — `Implementation/Phase_01..12_*.md` (12 files, follow in order)
- **Tech mapping** — `Implementation/00_Laravel_Bridge.md` (the bridge doc that maps v2 design to Laravel idioms)
- **3-group column policy matrix** — `Implementation/03_Three_User_Groups.md` (the source of truth for which fields each group may write)

---

*End of HANDOFF. Phase 06 (Follow Up Team dashboard) is next — see § 8.*