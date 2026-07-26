# Recommended Laravel 12 Project Structure

## Overview

This structure follows Laravel 12 conventions while accommodating the specific needs of the SBC Guest Management System. PHP 8.2+ is required for enum support.

```
sbc-guest-management/
│
├── app/
│   ├── Enums/
│   │   ├── Role.php                 # 9-value backed enum
│   │   ├── ContactedStatus.php      # 7-value backed enum
│   │   └── JoinWhen.php             # 3-value backed enum
│   │
│   ├── Models/
│   │   ├── User.php                 # Sanctum authenticatable, HasRoles
│   │   ├── Guest.php                # Core guest model
│   │   ├── ImpactCell.php           # Cell/small group model
│   │   ├── ImpactSubmission.php     # Flexible submission model
│   │   ├── NotificationRule.php     # Email notification rules
│   │   ├── SmtpSetting.php          # Singleton SMTP settings
│   │   ├── PasswordReset.php        # Password reset tokens
│   │   └── AuditLog.php             # Audit trail model
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── AuthController.php           # login, logout, me, profile, password
│   │   │   │   ├── UserController.php           # CRUD users
│   │   │   │   ├── GuestController.php          # CRUD guests, reassign
│   │   │   │   ├── CsvController.php            # CSV upload
│   │   │   │   ├── ReportController.php         # dashboard, audit, officer-performance
│   │   │   │   ├── ImpactCellController.php     # CRUD cells
│   │   │   │   ├── PublicImpactController.php   # public cells, public join
│   │   │   │   ├── ImpactSubmissionController.php # CRUD submissions, summary
│   │   │   │   └── NotificationController.php   # SMTP settings, rules, test
│   │   │   └── Controller.php                   # Base controller
│   │   │
│   │   ├── Middleware/
│   │   │   ├── CheckRole.php                    # requireRole equivalent
│   │   │   └── ActiveRole.php                   # X-Active-Role header handler
│   │   │
│   │   ├── Requests/
│   │   │   ├── StoreGuestRequest.php            # Guest create validation
│   │   │   ├── UpdateGuestRequest.php           # Guest update validation
│   │   │   ├── StoreUserRequest.php             # User create validation
│   │   │   ├── UpdateUserRequest.php            # User update validation
│   │   │   ├── LoginRequest.php                 # Login validation
│   │   │   ├── ForgotPasswordRequest.php        # Forgot password email
│   │   │   ├── ResetPasswordRequest.php         # Reset password validation
│   │   │   ├── ChangePasswordRequest.php        # Change password validation
│   │   │   ├── ReassignGuestRequest.php         # Guest reassign validation
│   │   │   ├── StoreSubmissionRequest.php       # Impact submission validation
│   │   │   ├── StoreNotificationRuleRequest.php # Notification rule validation
│   │   │   ├── UpdateSmtpRequest.php            # SMTP settings validation
│   │   │   └── TestEmailRequest.php             # Test email validation
│   │   │
│   │   ├── Resources/
│   │   │   ├── UserResource.php                 # User JSON response
│   │   │   ├── GuestResource.php                # Guest JSON response
│   │   │   ├── GuestCollection.php              # Guest collection response
│   │   │   ├── ImpactCellResource.php           # Cell JSON response
│   │   │   ├── ImpactSubmissionResource.php     # Submission JSON response
│   │   │   ├── NotificationRuleResource.php     # Rule JSON response
│   │   │   ├── SmtpSettingResource.php          # SMTP JSON response
│   │   │   ├── AuditLogResource.php             # Audit log JSON response
│   │   │   └── DashboardResource.php            # Dashboard data response
│   │   │
│   │   └── Responses/
│   │       └── ApiResponse.php                  # Standardized JSON response builder
│   │
│   ├── Services/
│   │   ├── GuestService.php                     # Guest business logic (sanitize)
│   │   ├── DashboardService.php                 # Dashboard aggregation queries
│   │   ├── CsvImportService.php                 # CSV parsing + import logic
│   │   ├── NotificationService.php              # Notification dispatch logic
│   │   ├── SmtpService.php                      # SMTP settings management
│   │   └── RoleService.php                      # Role normalization helpers
│   │
│   ├── Mail/
│   │   ├── GuestAssignedToImpactLeader.php      # Mailable for guest assignment
│   │   ├── ForgotPassword.php                   # Mailable for password reset
│   │   └── TestEmail.php                        # Mailable for SMTP test
│   │
│   ├── Notifications/
│   │   └── GuestAssignedNotification.php        # Notification channel
│   │
│   ├── Console/
│   │   └── Commands/
│   │       ├── SeedImpactCells.php              # Seed 70 impact cells
│   │       └── CleanupExpiredTokens.php         # Remove expired password resets
│   │
│   ├── Exceptions/
│   │   └── Handler.php                          # Global exception handler
│   │
│   └── Providers/
│       ├── AppServiceProvider.php               # App bootstrapping
│       └── RouteServiceProvider.php             # Route loading
│
├── config/
│   ├── api.php                                  # API configuration
│   ├── cors.php                                 # CORS settings
│   ├── sanctum.php                              # Sanctum configuration
│   ├── mail.php                                 # SMTP mail configuration
│   └── permissions.php                          # Role constants
│
├── database/
│   ├── migrations/
│   │   ├── 0001_create_users_table.php
│   │   ├── 0002_create_guests_table.php
│   │   ├── 0003_create_impact_cells_table.php
│   │   ├── 0004_create_impact_submissions_table.php
│   │   ├── 0005_create_notification_rules_table.php
│   │   ├── 0006_create_smtp_settings_table.php
│   │   ├── 0007_create_password_resets_table.php
│   │   └── 0008_create_audit_logs_table.php
│   │
│   ├── seeders/
│   │   ├── DatabaseSeeder.php                   # Main seeder
│   │   ├── AdminUserSeeder.php                  # Admin user seed
│   │   ├── ImpactCellSeeder.php                 # 70 impact cells seed
│   │   └── RolesSeeder.php                      # Role definitions (Spatie)
│   │
│   └── factories/
│       ├── UserFactory.php
│       ├── GuestFactory.php
│       └── ImpactSubmissionFactory.php
│
├── routes/
│   ├── api.php                                  # API routes (all endpoints)
│   ├── web.php                                  # Web routes (for Inertia/Blade pages)
│   └── console.php                              # Artisan commands
│
├── resources/
│   ├── js/                                      # React frontend (if keeping SPA)
│   │   ├── components/
│   │   ├── pages/
│   │   ├── hooks/
│   │   ├── services/
│   │   └── app.tsx
│   ├── css/                                     # Tailwind CSS
│   └── views/                                   # Blade views (if using Blade)
│
├── storage/
│   ├── app/
│   │   └── csv-uploads/                         # CSV file storage
│   ├── logs/
│   └── framework/
│
├── tests/
│   ├── Feature/
│   │   ├── Auth/
│   │   │   ├── LoginTest.php
│   │   │   ├── LogoutTest.php
│   │   │   ├── MeTest.php
│   │   │   ├── ForgotPasswordTest.php
│   │   │   └── ResetPasswordTest.php
│   │   ├── Guest/
│   │   │   ├── CreateGuestTest.php
│   │   │   ├── UpdateGuestTest.php
│   │   │   ├── DeleteGuestTest.php
│   │   │   ├── ReassignGuestTest.php
│   │   │   └── ListGuestsTest.php
│   │   ├── User/
│   │   │   ├── CreateUserTest.php
│   │   │   ├── UpdateUserTest.php
│   │   │   └── DeactivateUserTest.php
│   │   ├── Impact/
│   │   │   ├── PublicJoinTest.php
│   │   │   ├── CellManagementTest.php
│   │   │   └── SubmissionTest.php
│   │   ├── Report/
│   │   │   ├── DashboardTest.php
│   │   │   ├── AuditTest.php
│   │   │   └── OfficerPerformanceTest.php
│   │   ├── Notification/
│   │   │   ├── SmtpSettingsTest.php
│   │   │   ├── RuleManagementTest.php
│   │   │   └── TestEmailTest.php
│   │   └── Csv/
│   │       └── ImportTest.php
│   │
│   └── Unit/
│       ├── Enums/
│       │   ├── RoleTest.php
│       │   ├── ContactedStatusTest.php
│       │   └── JoinWhenTest.php
│       ├── Models/
│       │   ├── UserTest.php
│       │   ├── GuestTest.php
│       │   └── ImpactSubmissionTest.php
│       └── Services/
│           ├── GuestServiceTest.php
│           ├── CsvImportServiceTest.php
│           └── DashboardServiceTest.php
│
├── .env.example                                 # Environment template
├── composer.json                                # PHP dependencies
├── package.json                                 # Frontend dependencies
├── vite.config.js                               # Vite configuration
└── phpunit.xml                                  # Test configuration
```

## Key Design Decisions

### Services Layer
Extract business logic from controllers into dedicated service classes:
- `GuestService::sanitize()` — field validation and normalization
- `CsvImportService::import()` — CSV parsing, column mapping, duplicate detection
- `DashboardService::getStats()` — aggregation queries
- `NotificationService::notify()` — rule matching + email dispatch

### API Resources
Use Laravel API Resources for consistent response formatting:
- Enum value mapping (`ContactedStatus` enum → display string)
- Date formatting (ISO → DD-MM-YYYY)
- Nested relationship data
- Role normalization

### Spatie Laravel-Permission
Replace the custom `role` + `roles` JSON pattern:
```php
// Instead of: User.role (enum) + User.roles (JSON)
// Use: Spatie roles with a default role concept
$user->assignRole('Administrator');
$user->hasRole('Administrator');
```

### Enum Classes
PHP 8.1+ backed enums replace Prisma enums:
```php
public function labels(): array {
    return [
        'No' => 'No',
        'AvailableForVisit' => 'Available for Visit',
        // ...
    ];
}
```

### Service Provider for Impact Cells
Register the 70 hardcoded cell names as a config value:
```php
// config/impact-cells.php
return [
    'names' => [
        'ACO/JEDO', 'ASOKORO', // ...
    ],
];
```

### Command for Cell Seeding
```bash
php artisan impact-cells:seed
```
Ensures cells are created/updated in the database.

## Packages to Add

| Package | Purpose |
|---------|---------|
| `laravel/sanctum` | API token authentication |
| `spatie/laravel-permission` | Role and permission management |
| `spatie/laravel-ignition` | Error page (dev) |
| `maatwebsite/laravel-excel` | CSV import/export |
| `league/csv` | Alternative CSV parsing |
| `barryvdh/laravel-debugbar` | Debug toolbar (dev) |
| `laravel/horizon` | Queue monitoring (if using queues) |
| `pestphp/pest` | Testing framework |
