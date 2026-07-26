# HANDOFF — SBC Guest Management Portal (Laravel 12 build)

> **Audience:** a developer (or AI assistant) joining this repo mid-build.
> **Read time:** ~10 min to orient, ~30 min to feel competent.
> **Last updated:** end of **Phase 03 + Phase 02 UI wire (2026-07-26)** — see § 1 for build state.

This is the **build-state + next-steps** document. For the *design intent* (what we're building and why), see `Plan/` and `Implementation/`. For the *technology mapping* (which Laravel package / idiom replaces which old-stack idea), see `Implementation/00_Laravel_Bridge.md`.

---

## 0. TL;DR

We're rebuilding the SBC Guest Management app — a 3-user-group portal (Impact Cell, Follow Up Officer, Follow Up Team) for tracking "guests" through outreach → follow-up → integration — on a fresh **Laravel 12 + Inertia + React 19 + Breeze + Spatie Permission/Activitylog** stack. The MySQL 8.4 DB (`impact_guest` on localhost, user `ipcDBurs22`) is bootstrapped and live. **Phase 02 (auth + users + 3-group RBAC + active-role switching) is fully green with 40/40 verification assertions passing.** Next up is **Phase 03** (Impact Cell hierarchy).

---

## 1. Build state

| Phase | Doc | Status |
|---|---|---|
| 00 Pivot | — | ✅ Done — pivoted from Node/Prisma to Laravel 12 |
| 01 Foundation | `Phase_01_Foundation.md` | ✅ Done — Laravel 12.64.0 + Breeze + Spatie scaffold, `.env` configured, `storage:link` created, all baseline migrations applied |
| 02 Auth + Users | `Phase_02_Auth_And_Users.md` | ✅ **Done** — 9 roles seeded, `sbcadmin@impact.test` admin user, `RoleHelper` (3-group matrix), `User::activeRole()` accessor, `POST /auth/switch-role` route. **40/40 verification assertions pass.** |
| 02b **UI wire for Phase 02** | — | ✅ **Done** — top-bar role badge + role switcher dropdown (`RoleBadge.tsx`, `RoleSwitcher.tsx`), `HandleInertiaRequests` shares `auth.user.{activeRole,roles,hasMultipleRoles}`, stub pages render without 500. |
| 03 Impact Cell hierarchy | `Phase_03_Impact_Cell_Model.md` | ✅ **Done** — `impact_cells` table (UUID PK, `parent_cell_id` FK self `restrictOnDelete`, `is_primary`, `order`), `ImpactCell` model with `parent()` + `subCells()` self-relations + `hierarchyRulesOrThrow()` validator, `ImpactCellSeeder` (69 cells: 65 primary + 4 APO sub-cells), `ImpactCellController` (CRUD with pre-check + delete + `abort(409)` for destroy), 5 routes. **14/14 verification assertions pass.** |
| 04 Guest CRUD + column policy | `Phase_04_Guest_Records_Core.md` | ⏭ Next |
| 05–07 Dashboards (Officer / Team / Cell Leader) | `Phase_05..07_*.md` | ⏳ Pending |
| 08 Leadership Board | `Phase_08_Leadership_Board_UI.md` | ⏳ Pending |
| 09 Notifications + SMTP | `Phase_09_Notifications_SMTP.md` | ⏳ Pending |
| 10 CSV import/export | `Phase_10_CSV_Import_Export.md` | ⏳ Pending |
| 11 Reports + Audit | `Phase_11_Reports_And_Audit.md` | ⏳ Pending |
| 12 Deployment | `Phase_12_Deployment.md` | ⏳ Pending |

**Last verified green: Phase 02 (40/40) + Phase 03 (14/14), 2026-07-26.**
- `scripts/verify_phase02_run.php` — source of truth for "is Phase 02 still green?" — run after every Phase 02 edit.
- `scripts/verify_phase03_run.php` — source of truth for "is Phase 03 still green?" — run after every Phase 03 edit.
- If either prints anything other than `N pass / 0 fail`, fix the regression before continuing.

---

## 2. Quick start (5 min)

```bash
cd C:\laragon\www\impact_portal_plus

# 1. Confirm Laravel boots and DB is reachable
php artisan --version                        # → Laravel Framework 12.64.0
php artisan db:show                          # → impact_guest / 15+ tables

# 2. Run the Phase 02 verification suite — MUST print "40 pass / 0 fail"
php scripts/verify_phase02_run.php

# 3. Browse the app via Laragon's vhost
#    http://impact-portal.test/         (welcome page)
#    http://impact-portal.test/login    (Breeze login — sbcadmin@impact.test //Chris##101)
```

If `verify_phase02_run.php` doesn't pass 40/40, **do not proceed** — fix the regression first. See § 8.

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
| `impactCell` | `Impact_Leaders`, `Impact_Cell_Admin`, `Impact_Cell_Report` | `impactStatus`, `nearestImpactCell` |
| `followUpOfficer` | `FollowUpOfficer`, `Follow_UP_Admin` | `gender`, `maritalStatus`, `age`, `phone`, `address`, `contactedStatus`, `joinWhen`, `daysAvailable`, `comments`, `visited`, `visitedAt`, `indicatedToJoin`, `visitationStatus`, `feedback` |
| `followUpTeam` | `Follow_UP`, `Follow_UP_View_Only` | `followUpStatus`, `followUpContacts` |
| (no group) | `Administrator`, `Supervisor` | `Administrator` writes everything; `Supervisor` writes nothing on Guest |

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
│   ├── verify_phase02_run.php    ← 40-assertion Phase 02 verifier (run after every change)
│   ├── verify_phase03_run.php    ← 14-assertion Phase 03 verifier (run after every change)
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

---

## 7. Verification (must stay green)

```bash
# Phase 02 — 40-assertion regression net — exits non-zero on any regression
php scripts/verify_phase02_run.php

# Phase 03 — 14-assertion regression net — asserts hierarchy + split + idempotency
php scripts/verify_phase03_run.php

# Probe — confirms the 'hashed' cast fires for the seeded admin password
php scripts/probe_password_hash.php
```

**Phase 02 verifier asserts**: 9 roles seeded (with exact names), admin user has Administrator + active_role, `RoleHelper::groupOf` 11-case matrix, `canEditField` 11-case policy, `stripDisallowed` 4-case (Admin pass-through / Officer strip / null + unknown-role defensive), `/auth/switch-role` route registered, `allGroupOwnedFields` ≥ 10 field names.

**Phase 03 verifier asserts**: 69 rows seeded, 65 primary + 4 sub-cells, hierarchy invariant (every non-primary has `parent_cell_id`; no primary has a parent), the APO primary has exactly the 4 expected sub-cells, `subCells()->exists()` is true for APO (controller would 409) and false for a leaf primary, seeder idempotency (count + split re-applied on re-run).

**Run this after every Phase 02 edit** (and after migrations / seeders change). It is the regression net for everything Phase 02 touches.

Bonus password spot-check (proves the `'hashed'` cast fires):
```bash
php scripts/probe_password_hash.php
```
The script reads `sbcadmin@impact.test`'s stored password, asserts it's a real `$2y$` bcrypt hash, runs `Hash::check('//Chris##101', $hash)`, and exits non-zero if the cast ever silently logs plaintext. Use this instead of an inline `php -r "…"` — every PHP `$var` reference has to be escaped as `\$var` to survive bash variable interpolation, and Windows bash (Git Bash) handles the escaping inconsistently with PHP's own quoting rules. A standalone script is portable, copy-pasteable, and exits cleanly with a status code you can grep.

---

## 8. What's next — Phase 04 (Guest CRUD + column policy)

Outlined in `Implementation/Phase_04_Guest_Records_Core.md` + bridge doc § 5 (Guest schema) + bridge doc § 6 (Form Request `prepareForValidation()` pattern).

Concrete Phase 04 deliverables:
1. Migration: `guests` table — UUID PK, columns from `Implementation/02_Database_Schema.md`, `softDeletes()`, indexes per the spec. Add `nearest_impact_cell_id` FK (nullable, `restrictOnDelete`) once Phase 03 ships — schema mapping per bridge §5.
2. Model: `app/Models/Guest.php` — UUID PK via `HasUuids`, `use SoftDeletes`, `nearestImpactCell(): BelongsTo ImpactCell`, `submissions(): HasMany ImpactSubmission` (Phase 07+).
3. Form Request: `app/Http/Requests/GuestRequest.php` — `prepareForValidation()` calls `RoleHelper::stripDisallowed($this->user()?->activeRole(), $this->all())` + `$this->replace($merged)` so banned keys never reach the validator. Single source of truth per bridge §6.
4. API Resource: `app/Http/Resources/GuestResource.php` — masks hidden columns per group (e.g., impactCell group hides `followUpStatus`).
5. Policy: `app/Policies/GuestPolicy.php` — row-level authorization (Administrator always; otherwise the user must be assigned the guest).
6. Controller: `app/Http/Controllers/GuestController.php` — `index`/`show`/`store`/`update`/`destroy` returning `Inertia::render('Guests/Index', [...])` for the index.
7. **Phase 04 must also add `ImpactCellPolicy`** (Phase 03 follow-up per the inline TODO in `ImpactCellController`) — only Administrators can edit cells.
8. Extend `scripts/verify_phase04_run.php` with assertions: form-request `prepareForValidation` strips disallowed fields (e.g., FollowUpOfficer posting `impactStatus` + `followUpStatus` gets them removed before validation), API Resource hides columns per group.

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

*End of HANDOFF. Phase 04 awaits — see § 8.*