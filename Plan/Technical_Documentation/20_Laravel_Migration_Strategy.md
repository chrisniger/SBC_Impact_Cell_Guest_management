# Laravel Migration Strategy

## Overview

This document maps each component of the current Express.js + React SPA system to its Laravel 12 equivalent. The migration strategy focuses on maintaining feature parity while adopting Laravel conventions and ecosystem tools.

---

## Core Architecture Mapping

| Current System | Laravel 12 Equivalent | Notes |
|---------------|----------------------|-------|
| Express.js | Laravel Framework | Full-stack framework with MVC architecture |
| Node.js runtime | PHP 8.2+ runtime | Laravel requires PHP 8.1+ |
| Vite dev server | Laravel Vite plugin | Built-in Vite integration |
| TanStack Start (SSR) | Laravel Blade + Inertia.js | SSR via Inertia or Blade |

---

## Route Mapping

| Express Route | Laravel Equivalent |
|---------------|-------------------|
| `auth.routes.js` | `routes/api.php` — AuthController methods |
| `user.routes.js` | `routes/api.php` — UserController (API resource) |
| `guest.routes.js` | `routes/api.php` — GuestController (API resource) |
| `report.routes.js` | `routes/api.php` — ReportController (custom methods) |
| `impact.routes.js` | `routes/api.php` — ImpactController, PublicImpactController |
| `notification.routes.js` | `routes/api.php` — NotificationController |
| `csv.routes.js` | `routes/api.php` — CsvController |

**Laravel Route Pattern:**
```php
// Auth routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// Resource routes
Route::apiResource('/users', UserController::class)->middleware(['auth:sanctum', 'role:Administrator']);
Route::apiResource('/guests', GuestController::class)->middleware('auth:sanctum');
Route::post('/guests/{guest}/reassign', [GuestController::class, 'reassign'])->middleware('auth:sanctum');

// Impact routes (public)
Route::get('/impact/public/cells', [PublicImpactController::class, 'cells']);
Route::post('/impact/public/join', [PublicImpactController::class, 'join']);
```

---

## Controller Mapping

| Express Controller | Laravel Controller | Methods |
|-------------------|-------------------|---------|
| `auth.controller.js` | `AuthController` | login, logout, me, updateProfile, changePassword, forgotPassword, resetPassword |
| `user.controller.js` | `UserController` | index, store, update, deactivate |
| `guest.controller.js` | `GuestController` | index, show, store, update, destroy, reassign |
| `csv.controller.js` | `CsvController` | upload |
| `report.controller.js` | `ReportController` | dashboard, audit, officerPerformance |
| `impact.controller.js` | `ImpactCellController`, `PublicImpactController`, `ImpactSubmissionController` | listCells, listPublicCells, publicJoin, createCell, updateCell, listSubmissions, createSubmission, summary |
| `notification.controller.js` | `NotificationController` | actions, getSmtp, updateSmtp, list, create, update, destroy, test |

---

## Middleware Mapping

| Express Middleware | Laravel Middleware | Notes |
|-------------------|-------------------|-------|
| `requireAuth` | `auth:sanctum` | Laravel Sanctum token authentication |
| `requireRole(...roles)` | Custom `RoleMiddleware` | Checks `$request->user()->role` |
| `errorHandler` | `App\Exceptions\Handler` | Laravel's exception handler |
| `cors()` | Laravel CORS config | `config/cors.php` |
| `cookieParser()` | Laravel Cookie handling | Built-in |
| `morgan("tiny")` | Laravel logging | `config/logging.php` |

**Role Middleware Implementation:**
```php
// app/Http/Middleware/CheckRole.php
public function handle(Request $request, Closure $next, ...$roles) {
    if (!in_array($request->user()->role, $roles)) {
        return response()->json(['error' => 'Forbidden'], 403);
    }
    return $next($request);
}

// In RouteServiceProvider or routes:
Route::middleware(['auth:sanctum', 'role:Administrator,Follow_UP_Admin']);
```

---

## Database / ORM Mapping

| Prisma | Laravel | Notes |
|--------|---------|-------|
| `schema.prisma` | Database migrations | `database/migrations/` |
| `PrismaClient` | Eloquent models | `app/Models/` |
| Prisma enums | Laravel Enums | Backed enums (PHP 8.1+) |
| `prisma.$transaction` | `DB::transaction()` | Database transactions |
| `prisma.findMany` | `Model::all()`, `Model::where()->get()` | |
| `prisma.findUnique` | `Model::find()`, `Model::where()->first()` | |
| `prisma.create` | `Model::create()` | |
| `prisma.update` | `Model::update()` | |
| `prisma.delete` | `Model::delete()` | |
| `prisma.groupBy` | `Model::selectRaw()->groupBy()` | Aggregation queries |
| `$queryRaw` | `DB::select()` | Raw SQL queries |
| Prisma JSON fields | Laravel JSON casting | `$casts = ['roles' => 'json']` |

---

## Authentication Mapping

| Current | Laravel |
|---------|---------|
| JWT (jsonwebtoken) | Laravel Sanctum (token-based API auth) |
| `jwt.sign()` | `$user->createToken()` |
| `jwt.verify()` | Sanctum middleware (`auth:sanctum`) |
| localStorage token | Sanctum token (Bearer header) |
| `JWT_EXPIRES_IN` | Sanctum token expiration |
| bcryptjs | Laravel's built-in `Hash::make()` / `Hash::check()` |
| custom forgot/reset password | Laravel's built-in `Password::broker()` |
| `PasswordReset` model | Laravel's `password_resets` table |
| crypto.randomBytes | `Str::random()` or `Str::uuid()` |

### Auth Flow (Sanctum)

```php
// Login
public function login(Request $request) {
    $user = User::where('username', $request->username)
        ->orWhere('email', $request->username)
        ->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages(['username' => ['Invalid credentials']]);
    }

    return response()->json([
        'token' => $user->createToken('api')->plainTextToken,
        'user' => $user->only(['id', 'fullName', 'email', 'username', 'role', 'roles']),
    ]);
}
```

---

## Validation Mapping

| Current | Laravel |
|---------|---------|
| `sanitize()` function | Form Request validation |
| Manual field checks | Rule classes + `Validator` |
| `throw { status: 400, message }` | `ValidationException` |
| Frontend React Hook Form | Vue + VeeValidate or React Hook Form (if keeping React) |

**Form Request Example:**
```php
// app/Http/Requests/StoreGuestRequest.php
public function rules(): array {
    return [
        'guestName' => ['required', 'string', 'max:255'],
        'event' => ['nullable', 'in:COMBINED SERVICE,CHURCH 1,CHURCH 2,OTHER'],
        'contactedStatus' => ['nullable', Rule::enum(ContactedStatus::class)],
        'followUpStatus' => ['nullable', Rule::in(['NOT CONTACTED', 'CONTACTED', 'WRONG NUMBER', 'NOT REACHABLE'])],
        'visitationStatus' => ['nullable', Rule::in(['Visited', 'Pending'])],
        'followUpContacts' => ['nullable', 'array', 'max:3'],
    ];
}
```

---

## Frontend Strategy

### Option A: Keep React SPA (Recommended)
Keep the existing React 19 frontend, update API calls to point to Laravel backend.

**Changes needed:**
- Update `api.ts` base URL
- No routing changes needed (TanStack Router stays)
- No UI component changes

### Option B: Laravel + Inertia.js
Rewrite frontend using Inertia.js with React or Vue.

**Changes needed:**
- Replace TanStack Router with Laravel web routes
- Replace TanStack React Query with Inertia visit/props
- Rewrite all components in Inertia pattern
- All route definitions move to `routes/web.php`

### Option C: Laravel Blade + Alpine.js
Full server-rendered approach using Laravel Blade templates and Alpine.js for interactivity.

**Changes needed:**
- Complete frontend rewrite
- All state management moves to server
- Charts: use Chart.js with Alpine.js

---

## Feature-Specific Migration

### Role/Permission System

**Recommended approach:** Spatie Laravel-permission

```php
// Instead of custom role field + JSON roles array, use:
// app/Models/User.php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable {
    use HasRoles;
}

// Assign roles
$user->assignRole('Administrator');

// Check roles
$user->hasRole('Administrator');
$user->hasAnyRole(['Follow_UP', 'Follow_UP_Admin']);
```

**Migration path:**
1. Create roles in `RolesSeeder` matching the 9 existing roles
2. Migrate `User.role` + `User.roles` JSON to Spatie role assignments
3. Update all role checks to use Spatie methods

### UUID Primary Keys

Laravel migration with UUID:
```php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    // ...
});

// In User model:
use Illuminate\Support\Str;

protected static function boot() {
    parent::boot();
    static::creating(function ($model) {
        $model->{$model->getKeyName()} = (string) Str::uuid();
    });
}
```

Or use `ramsey/uuid` or Laravel's built-in `HasUuids` trait (Laravel 9+).

### JSON Fields

```php
// In Eloquent model:
protected $casts = [
    'roles' => 'array',              // For User.roles JSON
    'follow_up_contacts' => 'array', // For Guest.followUpContacts JSON
    'data' => 'array',               // For ImpactSubmission.data JSON
];
```

### File Uploads (CSV)

```php
// Laravel Storage
use Illuminate\Http\Request;

public function upload(Request $request) {
    $request->validate([
        'file' => 'required|file|mimes:csv,txt|max:5120', // 5MB
    ]);

    $path = $request->file('file')->store('csv-uploads');
    // Process with league/csv or Laravel Excel
}
```

**Recommended CSV parsing:** `league/csv` package or `maatwebsite/laravel-excel`.

### Email Notifications

```php
// config/mail.php — SMTP settings
// app/Mail/GuestAssignedToImpactLeader.php

use App\Mail\GuestAssignedToImpactLeader;
use Illuminate\Support\Facades\Mail;

public function reassign(Request $request, Guest $guest) {
    // ... reassign logic

    if ($guest->followOfficer->role === 'Impact_Leaders') {
        Mail::to($guest->followOfficer->email)
            ->send(new GuestAssignedToImpactLeader($guest));
    }
}
```

Replace `NotificationRule` with Laravel's notification system or a custom `NotificationRule` model with event-based dispatching.

### Dashboard Aggregation Queries

Replace raw Prisma queries with Laravel's query builder:

```php
$pendingContacts = Guest::where('contacted_status', 'No')
    ->when($assignedOnly, fn($q) => $q->where('follow_officer_id', $userId))
    ->count();

$byStatus = Guest::selectRaw('contacted_status, COUNT(*) as count')
    ->when($assignedOnly, fn($q) => $q->where('follow_officer_id', $userId))
    ->groupBy('contacted_status')
    ->get();

$monthlyGuests = Guest::selectRaw("DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as count")
    ->groupBy(DB::raw("DATE_FORMAT(date, '%Y-%m')"))
    ->orderBy('month')
    ->get();
```

### Multi-Role Switching

Current `X-Active-Role` header pattern can be ported directly:
```php
// Middleware
public function handle(Request $request, Closure $next) {
    $activeRole = $request->header('X-Active-Role');
    if ($activeRole && $request->user()->hasRole($activeRole)) {
        $request->user()->activeRole = $activeRole;
    }
    return $next($request);
}

// In controller
$user->activeRole // use instead of $user->role
```

Or implement role switching via session/database for server-rendered approaches.

---

## Enums Migration

```php
// app/Enums/Role.php
enum Role: string {
    case Administrator = 'Administrator';
    case Supervisor = 'Supervisor';
    case FollowUpOfficer = 'FollowUpOfficer';
    case Follow_UP = 'Follow_UP';
    case Follow_UP_Admin = 'Follow_UP_Admin';
    case Follow_UP_View_Only = 'Follow_UP_View_Only';
    case Impact_Leaders = 'Impact_Leaders';
    case Impact_Cell_Admin = 'Impact_Cell_Admin';
    case Impact_Cell_Report = 'Impact_Cell_Report';
}

// app/Enums/ContactedStatus.php
enum ContactedStatus: string {
    case No = 'No';
    case Yes = 'Yes';
    case AvailableForVisit = 'AvailableForVisit';
    case NotAvailableForVisit = 'NotAvailableForVisit';
    case NotReachable = 'NotReachable';
    case WrongNumber = 'WrongNumber';
    case Others = 'Others';
}

// app/Enums/JoinWhen.php
enum JoinWhen: string {
    case FirstTimer = 'FirstTimer';
    case NewMember = 'NewMember';
    case OldMember = 'OldMember';
}
```

---

## Migration Priority Order

1. **Database:** Create migrations, seed data, verify schema
2. **Models:** Create Eloquent models with casts, relationships, and scopes
3. **Enums:** Create PHP backed enums
4. **Authentication:** Implement Sanctum, verify login/logout
5. **Middleware:** Implement auth:sanctum + custom RoleMiddleware
6. **Controllers:** Port each controller one by one, starting with Auth
7. **Form Requests:** Create validation rules
8. **Routes:** Define all API routes
9. **Mail/Nofitications:** Port mailer and notification system
10. **CSV Import:** Port file upload and parsing
11. **Frontend:** Update API endpoints (minimal if keeping React)
12. **Testing:** Verify all endpoints with feature tests

---

## Potential Challenges

| Challenge | Mitigation |
|-----------|------------|
| Prisma JSON fields → MySQL JSON columns | Use `$casts = ['field' => 'array']` for transparent JSON handling |
| UUID primary keys | Use `HasUuids` trait or `Str::uuid()` in model boot method |
| Multi-role with primary role | Use Spatie permissions or maintain `role` + `roles` pattern |
| Enum mapping (API↔Display) | Use Laravel Enum `labels()` method or API resources |
| Active role switching (X-Active-Role) | Custom middleware + request macro |
| Client-side filtering | Move to server-side query parameters or keep client-side |
| Recharts charts | Keep React for charts or use Chart.js with Laravel Excel |
| ImpactSubmission JSON flexibility | Use JSON column with schema validation via Form Requests |
