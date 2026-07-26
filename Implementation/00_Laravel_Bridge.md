# 00 — Laravel Bridge (Laravel 12 + Inertia + React 19)

> Rosetta Stone between the v2 design and the **Laravel 12** stack we're actually building.  
> Treat the 19 other Implementation/ docs as design intent — this is the only doc that names Laravel/Spatie/Sanctum/Inertia specifically. Where a PHP idiom differs from the equivalent Express/Prisma one, that's here.

---

## 1. Why a bridge

We pivoted from a Node/Express/Prisma/React 19 stack to **Laravel 12 + Inertia.js + React 19 + TanStack Router**. The 19 existing implementation docs were authored during the prior assumption; they remain valid for the *intent* (column-access matrix, leadership board, design system), but Laravel has clear idioms we should use instead of re-implementing Express middleware. This doc enforces that.

## 2. Component mapping (Node/Express → Laravel)

| Responsibility | Before (Node/Express) | After (Laravel 12) |
|---|---|---|
| HTTP framework | Express.js | Laravel routing + controllers |
| ORM + schema | Prisma (`schema.prisma`) | Eloquent models + database migrations (UUID PKs) |
| Auth (login / password reset) | JWT + bcryptjs + custom middleware | **Laravel Sanctum** with stateful cookie auth (Inertia pairs naturally) + Breeze starter |
| Roles (9 roles + 3 groups) | Custom `roles.js` arrays + `requireRole()` middleware | **Spatie Laravel-Permission** for the 9 roles + a small `RoleHelper` class for the 3 groups |
| Column-level access policy | `server/lib/access.js` + `stripDisallowed()` | **Form Requests** (strip inputs) + **Eloquent API Resources** (hide outputs) + **Laravel Policies** (row-level) |
| Soft deletes | Manual `deletedAt` columns in schema | `use SoftDeletes;` trait + `$table->softDeletes()` in migrations |
| Impact Cell hierarchy | Self-relation in Prisma | Same — `parent()` `belongsTo` / `subCells()` `hasMany` on the same `ImpactCell` model |
| Leadership Board rollup | Custom `DashboardCache` Prisma model | **Skip the table.** Use `Cache::remember("board_{$id}", 300, fn() => ...)` — Laragon has Redis/File/Database cache drivers out of the box, zero schema footprint |
| Audit log | Custom `AuditLog` Prisma model + `server/lib/audit.js` | **Spatie Laravel-Activitylog** — native Eloquent observer tracks causer, subject, before/after automatically |
| Dashboard charts | Recharts via Express JSON | Recharts via Inertia props — same library, no build change |
| CSV import | `csv-parse` + Multer + custom dedup | **maatwebsite/excel** (Laravel Excel) |
| SMTP / mail | `nodemailer` + `NotificationRule` table | Laravel `Mail` facade + `Illuminate\Notifications\Notification` |
| Avatar / receipt uploads | Base64 data URL inside `Guest`/`ImpactSubmission.data` JSON | Drop the data-URL pattern. Use Inertia multipart uploads → `Storage::disk('public')->put()` |
| Public join form | `GET /api/impact/public/cells` + `POST /api/impact/public/join` | Same endpoints — keep unauthenticated Inertia page (no middleware) |

## 3. Architectural decisions (locked for v2)

1. **Auth = Sanctum (stateful, cookie-based).** No JWTs. Sanctum's stateful guard + Inertia is the textbook Laravel combo for a "professional beautiful" SPA feel without exposing tokens.
2. **Roles = Spatie Laravel-Permission.** DB-backed `roles` table with the 9 enum values seeded as roles. The 3 *groups* live as static arrays/enum in `app/Support/RoleHelper.php`.
3. **Audit = Spatie Laravel-Activitylog.** Every Guest, ImpactSubmission, User mutation logs `causer_id`, `subject_type`, `subject_id`, and a JSON diff. Replaces both the `AuditLog` enum enrichment *and* the `server/lib/audit.js` helper.
4. **Dashboard cache = Cache facade.** TTL 5 min via `Cache::remember`. Cache driver: `file` for local Laragon dev (zero extra services), `redis` if you enable Redis in Laragon.
5. **CSV = Laravel Excel.** Built-in queue/batch/chunk handling. Duplicate-by-phone detection via `Collection::pluck` then `whereNotIn`.
6. **Frontend = Inertia + React 19 + Vite + Tailwind v4 + Radix.** Drop Express JSON API; controllers return `Inertia::render('Page', props)`. (See § 7 — TanStack Router caveat.)

## 4. Scaffold sequence (Windows/Laragon — PHP 8.2+, Composer 2.x)

```bash
# 1. Drop into the Laragon web root.
cd C:\laragon\www

# 2. Create the project inside *this* directory (impact_portal_plus).
#    Composer requires an empty target, so we scaffold into a temp
#    folder then merge, OR we use `composer create-project --no-install`
#    and copy laravel/* files into the existing folder.
composer create-project --prefer-dist laravel/laravel:^12.0 _laravel_stub
# Move every file from _laravel_stub/* INTO C:\laragon\www\impact_portal_plus\
# then `rmdir /s /q _laravel_stub`.

cd impact_portal_plus

# 3. Initialize the frontend stack (Breeze gives us Inertia + React + TS).
composer require laravel/breeze --dev
php artisan breeze:install react --typescript --dark

# 4. Core domain packages.
composer require \
  spatie/laravel-permission \
  spatie/laravel-activitylog \
  maatwebsite/excel

# 5. Configure .env (the one the user gave us for Laravel).
#    DB_CONNECTION=mysql
#    DB_HOST=127.0.0.1
#    DB_PORT=3306
#    DB_DATABASE=impact_guest
#    DB_USERNAME=ipcDBurs22
#    DB_PASSWORD="REPLACE_ME_WITH_REAL_PASSWORD"   # ^ is literal in .env — Laravel URL-encodes when building the DSN
#    CACHE_STORE=file
#    SESSION_DRIVER=database
#    APP_URL=http://impact-portal.test   # Laragon auto-vhost
php artisan key:generate
php artisan storage:link   # symlink public/storage → storage/app/public — required for avatar/receipt uploads via Storage::disk('public')

# 6. Publish package migrations.
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"

# 7. Run the first migration.
php artisan migrate

# 8. Frontend deps + Vite (starts on :5173 but the *app* is served by Laragon).
npm install
npm run dev
```

> 💡 `.env` lives only in this project root. Never commit. The `.env.example` should publish a *placeholder* URL; users edit locally.

## 5. Schema mapping (Prisma v2 → Eloquent migrations)

| v2 design (Implementation/02_Database_Schema.md) | Eloquent migration shape |
|---|---|
| `id String @id @default(uuid())` | `$table->uuid('id')->primary();` + model `public $incrementing = false; protected $keyType = 'string';` |
| `User.roles Json?` | Drop the JSON. Spatie creates the `model_has_roles` pivot (`role_id`, `model_type`, `model_id`). |
| `User.active_role (multi-role switching)` | `$table->string('active_role')->nullable();` — persists the role a multi-role user is currently viewing. `Auth::user()->active_role` is read by `RoleHelper` and `prepareForValidation`. Phase 02 adds a `POST /auth/switch-role` route that updates this column and refreshes the session. |
| `Guest.deletedAt DateTime?` | `$table->softDeletes();` + `use Illuminate\Database\Eloquent\SoftDeletes;` on the model |
| `ImpactCell.parentCellId` self-relation | `$table->foreignUuid('parent_cell_id')->nullable()->constrained('impact_cells')->restrictOnDelete();`. **NOT `nullOnDelete`.** Sub-cells MUST have a parent per `Implementation/04_Impact_Cell_Hierarchy.md::validateHierarchy()`; `restrictOnDelete` makes MySQL throw if a primary is deleted while sub-cells exist. The `ImpactCellController@destroy` handler must first promote children to primaries OR 409 if any sub-cells exist. |
| `ImpactCell.isPrimary + order` | `$table->boolean('is_primary')->default(false); $table->integer('order')->default(0); $table->index(['parent_cell_id']); $table->index(['is_primary']);` |
| `ImpactSubmission.deletedAt + indexes` | softDeletes + composite indexes (`type, impact_cell_id`, `impact_cell_id, type, fellowship_date_key`) |
| `AuditLog` table | **Drop entirely.** Spatie creates `activity_log` with `log_name`, `description`, `subject_type`, `subject_id`, `causer_type`, `causer_id`, `properties` (JSON), `created_at` — covers everything we designed. |
| `DashboardCache` table | **Drop entirely.** Use `Cache::remember`. |
| 70 hardcoded impact cells | `ImpactCellSeeder` writes them on first migrate. Sets `is_primary = true`, `parent_cell_id = NULL`, `order = alphabetical index`. |
| Default admin user | `AdminUserSeeder` — username `sbcAdmin`, password `//Chris##101`, role `Administrator`, `active = true`. |

## 6. 3 user groups + 9 roles

The 9 roles stay as Spatie roles:
`Administrator`, `Supervisor`, `FollowUpOfficer`, `Follow_UP`, `Follow_UP_Admin`, `Follow_UP_View_Only`, `Impact_Leaders`, `Impact_Cell_Admin`, `Impact_Cell_Report`.

The 3 groups live in `app/Support/RoleHelper.php`:

```php
final class RoleHelper {
  public const GROUP_IMPACT_CELL      = ['Impact_Leaders', 'Impact_Cell_Admin', 'Impact_Cell_Report'];
  public const GROUP_FOLLOW_UP_OFFICER = ['FollowUpOfficer', 'Follow_UP_Admin'];
  public const GROUP_FOLLOW_UP_TEAM    = ['Follow_UP', 'Follow_UP_View_Only'];

  public const GROUP_GUEST_OWNER = [
    'impactCell'      => ['impactStatus', 'nearestImpactCell'],
    'followUpOfficer' => [
      'gender','maritalStatus','age','phone','address',
      'contactedStatus','joinWhen','daysAvailable','comments',
      'visited','visitedAt','indicatedToJoin','visitationStatus','feedback',
    ],
    'followUpTeam'    => ['followUpStatus', 'followUpContacts'],
  ];

  public static function groupOf(?string $role): ?string {
    if (!$role) return null;
    return match (true) {
      in_array($role, self::GROUP_IMPACT_CELL, true)        => 'impactCell',
      in_array($role, self::GROUP_FOLLOW_UP_OFFICER, true)   => 'followUpOfficer',
      in_array($role, self::GROUP_FOLLOW_UP_TEAM, true)      => 'followUpTeam',
      default                                               => null,
    };
  }

  public static function canEditField(?string $role, string $field): bool {
    if (!$role) return false;
    if ($role === 'Administrator') return true;
    $g = self::groupOf($role);
    return $g !== null && in_array($field, self::GROUP_GUEST_OWNER[$g], true);
  }

  public static function stripDisallowed(?string $role, array $body): array {
    if ($role === 'Administrator') return $body;
    if (self::groupOf($role) === null) return []; // unknown role → strip everything
    return array_filter(
      $body,
      fn ($_, $key) => self::canEditField($role, $key),
      ARRAY_FILTER_USE_BOTH
    );
  }
}
```

Then in `GuestController` Form Requests (wired into `prepareForValidation()` so the keys never reach the validator):

```php
use App\Models\User;
use App\Support\RoleHelper;

class GuestRequest extends FormRequest {
  protected function prepareForValidation(): void {
    // Single source of truth — NEVER inline `$user->active_role ?? $user->getRoleNames()->first()`.
    // The accessor handles stale-column fallback, first-Spatie-role, and null defensively.
    $role   = $this->user()?->activeRole();
    $merged = RoleHelper::stripDisallowed($role, $this->all());
    $this->replace($merged);
  }

  public function rules(): array {
    return [
      'guest_name' => ['required', 'string', 'max:255'],
      'phone'      => ['nullable', 'string', 'max:32'],
      'address'    => ['nullable', 'string', 'max:255'],
      'age'        => ['nullable', 'integer', 'min:0', 'max:130'],
      // …
    ];
  }
}
```

> ⚠️ Don't call `$this->merge(stripped)` inside `rules()` — that runs AFTER validation routing. The `prepareForValidation()` hook is the right place so the banned keys are gone before the validator inspects them.

**`User` model must include the Spatie trait:**
```php
use Spatie\Permission\Traits\HasRoles;
class User extends Authenticatable {
  use HasRoles;
  // add `active_role` column via migration below
  public function activeRole(): ?string { return $this->active_role ?? $this->getRoleNames()->first(); }
}
```

The matrix in `Implementation/03_Three_User_Groups.md` is the source of truth for what's editable per group.

## 7. Pitfalls & warnings

> ⚠️ Read this carefully before scaffolding — these are real gotchas a developer hits on day one.

1. **MySQL 8.4 — bootstrap the user with `caching_sha2_password` (NOT `mysql_native_password`).**
   MySQL 8.4 ships with `caching_sha2_password` as the active default. The legacy `mysql_native_password` plugin is **DISABLED** in 8.4 (you'll get error `1524 Plugin 'mysql_native_password' is not loaded` if you try). PHP 8's PDO/mysqlnd handles `caching_sha2_password` natively — so do Laravel and Doctrine. One-time bootstrap as `root` (Laragon default has empty root password):

   ```sql
   CREATE DATABASE IF NOT EXISTS impact_guest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'ipcDBurs22'@'localhost' IDENTIFIED WITH caching_sha2_password BY 'YOUR_PASSWORD_HERE';
   GRANT ALL PRIVILEGES ON impact_guest.* TO 'ipcDBurs22'@'localhost';
   FLUSH PRIVILEGES;
   ```

   That single sequence has been verified on this stack (MySQL 8.4.3 + PHP 8.3 + Laragon). Smoke test: `php artisan db:show` should print the connection details and table counts. If you ever hit `Access denied` after a successful CREATE, the user gave you the wrong password in the bootstrap — just re-run with the corrected string.
2. **Inertia + TanStack Router coexistence (user explicitly chose both).**
   Inertia **is** a router, and TanStack Router is a client-side router. The marriage works, but you must set the boundary clearly:
   - **TanStack Router** owns in-page navigation between sub-routes within a single Inertia page (e.g. switching `?section=member` to `?section=reports` inside the Dashboard page is *TanStack* territory; full-page navigations to `/users` is *Inertia* territory).
   - Use TanStack Router with a single root route whose component is `<Outlet />` (or the equivalent Inertia-aware wrapper). This way TanStack only renders sub-components, and Inertia hands over the canonical URL to Laravel routes when the path changes.
   - Configure `createRouter({ defaultPreload: false, history: createBrowserHistory() })` so Inertia-driven full navigations don't double-fire.
   - Either way, scrap the TanStack `<Link>` for full-page navigation — keep using Inertia's `<Link>` there.
   - If this turns into more friction than benefit, fallback is to remove TanStack Router and rely purely on Inertia + simple `<Link>` to sub-pages; mention this as a fallback in the Phase 04 review.
3. **Cross-platform paths.**
   All file storage MUST go through Laravel's `Storage` facade (`Storage::disk('public')`). Hardcoded `C:\…` paths will break on the Linux Hostinger deploy.
4. **Local dev URL.**
   Vite defaults to `http://localhost:5173`. Sanctum *stateful* auth needs the Inertia page to load from the **Laragon vhost** (e.g. `http://impact-portal.test`), not from `:5173`. Otherwise the session cookie gets a wrong-domain attachment. Configure `APP_URL` in `.env` to match your Laragon hostname. Access the app via the Laragon URL.
5. **Composer install speed.**
   Laragon MySQL may not start before `php artisan migrate`. Quick check: `php artisan db:show` — if `Connection refused`, run `laragon start MySQL` (or the tray icon).
6. **Vite proxy + Sanctum.**
   Breeze stubs a `vite.config.js` with a Sanctum-aware proxy. If you ever edit it, keep `server.proxy['/']` pointing at the Laragon URL, not `:3000`.
7. **Spatie permissions cache.**
   When seeding roles, call `app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions()`. Otherwise newly-seeded role/permission changes don't appear until you `php artisan cache:clear`.
8. **Activity log verbosity.**
   Spatie Activitylog will log every `updated` event automatically. We want logged *but* only on specific controllers. Set the `$logAttributes` array explicitly per model so we don't pollute the table with noise.
9. **Windows / Laragon — `composer require` hit by `Access is denied (code: 5)` on `bootstrap\cache\services.php`.**
   On **Laragon** specifically the most common cause is the **Watch** file-monitor service in the tray menu scanning `bootstrap/cache/` mid-rename. Toggle **Watch OFF** in the Laragon tray, re-run the composer command, then re-enable Watch. The manual fallback (whatever the cause) is:
   ```bash
   rm -f bootstrap/cache/*.tmp bootstrap/cache/services.php bootstrap/cache/packages.php
   composer require … --no-scripts   # --no-scripts bypasses the post-autoload rename
   ```
   Common root causes: Laragon Watch, antivirus (Defender/Avast scanning `vendor/`), or a leftover zombie `php artisan serve` holding services.php open. Verify with PowerShell: `Get-Process | Where-Object { $_.MainWindowTitle -like '*artisan*' }` should be empty when you re-run.
10. **Spatie migrations are `.stub` files — they are NOT auto-discovered by Laravel 12 migration discovery.**
    `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"` returns "No publishable resources" in Spatie v8.x. You must:
    ```bash
    cp vendor/spatie/laravel-permission/config/permission.php                  config/permission.php
    cp vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub  database/migrations/YYYY_MM_DD_HHMMSS_create_permission_tables.php
    cp vendor/spatie/laravel-activitylog/database/migrations/create_activity_log_table.php.stub  database/migrations/YYYY_MM_DD_HHMMSS_create_activity_log_table.php
    ```
    …then run `php artisan migrate`. Spatie's `loadMigrationsFrom()` in the package service provider only fires when discovery is explicitly enabled in `bootstrap/app.php` (which Laravel 12 does NOT do by default).

## 8. Phase-to-Laravel summary

| Phase | Implementation/ doc | Laravel translation |
|---|---|---|
| 01 Foundation | `Phase_01_Foundation.md` | composer create-project + `php artisan key:generate` + `php artisan migrate`. |
| 02 Auth + Users | `Phase_02_Auth_And_Users.md` | Breeze + Sanctum + Spatie permissions + `Breeze\\Auth\SendsPasswordResetLinks` is already wired. Add `POST /auth/switch-role` route (writes `users.active_role`) per § 5 multi-role switching. `User` model uses `HasRoles` trait + `activeRole()` accessor per § 6. |
| 03 Impact Cell hierarchy | `Phase_03_Impact_Cell_Model.md` | Migration: `$table->foreignUuid('parent_cell_id')->nullable()->constrained('impact_cells')->restrictOnDelete();` + Eloquent `parent()` / `subCells()` methods. Controller rejects delete-with-children (409) per §5 hierarchy rules. |
| 04 Guest CRUD + column policy | `Phase_04_Guest_Records_Core.md` | `GuestRequest` (Form Request using `RoleHelper::stripDisallowed`) + `GuestPolicy`. |
| 05 Follow Up Officer dashboard | `Phase_05_Follow_Up_Officer.md` | `DashboardController@officer` returns `Inertia::render('Dashboard/Officer', [...])`. |
| 06 Follow Up Team dashboard | `Phase_06_Follow_Up_Team.md` | `DashboardController@team` + inline `GuestController@updateStatus` via Inertia partial reloads. |
| 07 Impact Cell Leader forms | `Phase_07_Impact_Cell_Leader.md` | `SubmissionController` (one per submission type) + `ImpactSubmissionRequest` validations. |
| 08 Leadership Board UI | `Phase_08_Leadership_Board_UI.md` | `DashboardService::board($cellId)` → `Cache::remember("board_{$id}", 300, ...)`. One Blade/React page. |
| 09 Notifications + SMTP | `Phase_09_Notifications_SMTP.md` | Laravel `Mail` + `Notification`. SMTP settings live in `config/mail.php` + a settings table for runtime edits. |
| 10 CSV import/export | `Phase_10_CSV_Import_Export.md` | `CsvImport` class extending `maatwebsite/excel`. 3 import classes (`FollowUpOfficerImport`, `FollowUpTeamImport`, `ImpactCellImport`). |
| 11 Reports + Audit | `Phase_11_Reports_And_Audit.md` | `ReportController@dashboard` (Eloquent aggregations) — audit is auto via Spatie Activitylog. |
| 12 Deployment | `Phase_12_Deployment.md` | Hostinger Node sections don't apply. Replace with: `composer install --optimize-autoloader`, `php artisan migrate --force`, `npm run build`, point host at `public/`. |

---

## 9. Cross-reference index (design → Laravel)

If you came looking for: → read this:

- The 9 roles and the 3 user groups → `Implementation/03_Three_User_Groups.md` (matrix) + this doc § 6 (mapping).
- The Impact Cell hierarchy rules → `Implementation/04_Impact_Cell_Hierarchy.md` + this doc § 5 (Eloquent shape) + § 6 (RoleHelper).
- The Leadership Board spec → `Implementation/05_Leadership_Board.md` (UI/UX) + this doc § 3 (decision: Cache::remember, not custom table).
- The Dashboard design tokens (palette, type, KPI cards, charts) → `Implementation/06_Dashboard_Design_System.md`. Tailwind v4 config + Radix shadcn port — Laravel doesn't change this.
- API contract (request/response shapes) → `Plan/Technical_Documentation/05_API_Documentation.md`. Translate each endpoint to an Inertia route + controller; the JSON shape stays identical.

---
*Next: `Phase_01_Foundation.md` (Laravel-ised in § 8).*
