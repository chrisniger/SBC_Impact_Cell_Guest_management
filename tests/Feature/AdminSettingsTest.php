<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Support\BackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 33 — Admin Settings (SMTP config + Backup & Restore) feature test.
 *
 * Locks the user-visible contract:
 *   1. Page is Administrator-only (403 for other roles).
 *   2. SMTP save writes MAIL_* keys into a temp .env via EnvWriter and
 *      leaves the password untouched when the field is blank.
 *   3. Test email endpoint applies candidate values on-the-fly (no write).
 *   4. Backup downloads a JSON archive per scope (full / impact_cell /
 *      follow_up_officer / follow_up_team) with the right table coverage.
 *   5. Restore only accepts a FULL archive; it wipes + re-inserts
 *      business tables transactionally.
 *
 * Isolation: the SMTP save writes to a TEMP env file (config
 * `settings.env_path` pointed at storage/tmp), NOT the real .env, so a
 * test run can never clobber dev credentials.
 */
class AdminSettingsTest extends TestCase
{
    // NOTE: deliberately does NOT `use RefreshDatabase` again here. The base
    // Tests\TestCase already composes RefreshDatabaseWithSeed (which rebinds
    // the connection to the isolated `impact_test` DB inside
    // beforeRefreshingDatabase()). Adding a second `use RefreshDatabase` at
    // the class level makes PHP's trait precedence pick the EMPTY base-trait
    // `beforeRefreshingDatabase()` over the rebind override — tests then run
    // against the LIVE impact_guest dev DB. The double-use is harmful here.

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Redirect EnvWriter to a throwaway file for the duration of the test.
        Config::set('settings.env_path', storage_path('framework/testing/smtp_test.env'));
        @mkdir(dirname(Config::get('settings.env_path')), 0777, true);
        @unlink(Config::get('settings.env_path'));
    }

    // ─── Sub-assertion 1 — page loads for admin with smtp payload ─────
    public function test_admin_can_view_settings_page(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Index')
            ->has('smtp')
            ->where('mailConfigured', false)
            ->has('backupScopes', 4)
        );
    }

    // ─── Sub-assertion 2 — non-admin gets 403 ─────────────────────────
    public function test_non_admin_cannot_view_settings_page(): void
    {
        $leader = $this->makeUserWithRole('Impact_Leaders');

        $this->actingAs($leader)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($leader)->post(route('admin.settings.smtp.store'), [])->assertForbidden();
        $this->actingAs($leader)->post(route('admin.settings.smtp.test'), [])->assertForbidden();
        $this->actingAs($leader)->get(route('admin.settings.backup'))->assertForbidden();
        $this->actingAs($leader)->post(route('admin.settings.restore'), [])->assertForbidden();
    }

    // ─── Sub-assertion 3 — SMTP save writes .env + skips blank password ─
    public function test_smtp_save_writes_env_and_preserves_blank_password(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $envPath = Config::get('settings.env_path');
        file_put_contents($envPath, "APP_NAME=\"Impact Cell | Guest Portal\"\nMAIL_MAILER=log\nMAIL_PASSWORD=oldsecret\n");

        $response = $this->actingAs($admin)->post(route('admin.settings.smtp.store'), [
            'mailer'       => 'smtp',
            'host'         => 'smtp.gmail.com',
            'port'         => 587,
            'username'     => 'no-reply@impact.test',
            'password'     => '', // blank → keep existing
            'scheme'       => 'tls',
            'from_address' => 'no-reply@impact.test',
            'from_name'    => 'Impact Portal',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $env = file_get_contents($envPath);
        $this->assertStringContainsString('MAIL_MAILER=smtp', $env);
        $this->assertStringContainsString('MAIL_HOST=smtp.gmail.com', $env);
        $this->assertStringContainsString('MAIL_PORT=587', $env);
        $this->assertStringContainsString('MAIL_SCHEME=tls', $env);
        // @ and . don't trigger EnvWriter quoting → written bare.
        $this->assertStringContainsString('MAIL_FROM_ADDRESS=no-reply@impact.test', $env);
        // Blank password preserved the existing credential.
        $this->assertStringContainsString('MAIL_PASSWORD=oldsecret', $env);
    }

    // ─── Sub-assertion 4 — new password IS written when provided ──────
    public function test_smtp_save_writes_new_password_when_provided(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $envPath = Config::get('settings.env_path');
        file_put_contents($envPath, "MAIL_PASSWORD=oldsecret\n");

        $this->actingAs($admin)->post(route('admin.settings.smtp.store'), [
            'mailer'       => 'smtp',
            'host'         => 'smtp.gmail.com',
            'port'         => 465,
            'username'     => 'no-reply@impact.test',
            'password'     => 'newsecret',
            'scheme'       => 'ssl',
            'from_address' => 'no-reply@impact.test',
            'from_name'    => 'Impact Portal',
        ]);

        $env = file_get_contents($envPath);
        $this->assertStringContainsString('MAIL_PASSWORD=newsecret', $env);
        $this->assertStringNotContainsString('MAIL_PASSWORD=oldsecret', $env);
    }

    // ─── Sub-assertion 4b — save refreshes the RUNNING request's mail config ──
    public function test_smtp_save_refreshes_mail_config_for_current_request(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $envPath = Config::get('settings.env_path');
        file_put_contents($envPath, "MAIL_MAILER=log\n");

        $this->actingAs($admin)->post(route('admin.settings.smtp.store'), [
            'mailer'       => 'smtp',
            'host'         => 'smtp.gmail.com',
            'port'         => 587,
            'username'     => 'no-reply@impact.test',
            'password'     => 'newsecret',
            'scheme'       => 'tls',
            'from_address' => 'no-reply@impact.test',
            'from_name'    => 'Impact Portal',
        ]);

        // Same request (test shares the app instance with the controller) —
        // the in-memory config must already reflect the saved values, so a
        // follow-up Mail:: send in this request uses them immediately.
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('newsecret', config('mail.mailers.smtp.password'));
        $this->assertSame('tls', config('mail.mailers.smtp.scheme'));
        $this->assertSame('no-reply@impact.test', config('mail.from.address'));
    }

    // ─── Sub-assertion 5 — full backup covers all business tables ─────
    public function test_full_backup_includes_all_business_tables(): void
    {
        $this->seedFixtures();

        $payload = (new BackupService())->export(BackupService::SCOPE_FULL);

        $this->assertSame('impact_portal_backup', $payload['format']);
        $this->assertSame('full', $payload['scope']);
        foreach (['impact_cells', 'users', 'impact_cell_user', 'guests', 'impact_submissions', 'notification_settings'] as $table) {
            $this->assertArrayHasKey($table, $payload['tables']);
        }
        $this->assertCount(1, $payload['tables']['impact_cells']);
        $this->assertCount(2, $payload['tables']['guests']);
        $this->assertCount(1, $payload['tables']['impact_submissions']);
        // Role assignments must survive a full restore (reviewer catch).
        $this->assertNotEmpty($payload['tables']['model_has_roles']);
        // Row ORDER is not guaranteed (UUID PKs) — match by value.
        $this->assertContains(
            'Test Data Guest',
            collect($payload['tables']['guests'])->pluck('guest_name')->all()
        );
    }

    // ─── Sub-assertion 6 — segment scopes cover only their domain ─────
    public function test_segment_backups_scope_to_their_domain(): void
    {
        $this->seedFixtures();
        $service = new BackupService();

        $cell = $service->export(BackupService::SCOPE_IMPACT_CELL);
        $this->assertSame('impact_cell', $cell['scope']);
        $this->assertNotEmpty($cell['tables']['impact_cells']);
        $this->assertNotEmpty($cell['tables']['impact_submissions']);
        $this->assertNotEmpty($cell['tables']['users']);
        $this->assertArrayNotHasKey('notification_settings', $cell['tables']);

        $officer = $service->export(BackupService::SCOPE_FOLLOW_UP_OFFICER);
        $this->assertSame('follow_up_officer', $officer['scope']);
        $this->assertArrayNotHasKey('impact_cells', $officer['tables']);
        // The officer-role user is included; the plain follower is not.
        $officerEmails = collect($officer['tables']['users'])->pluck('email')->all();
        $this->assertContains('officer@impact.test', $officerEmails);
        $this->assertNotContains('follower@impact.test', $officerEmails);

        $team = $service->export(BackupService::SCOPE_FOLLOW_UP_TEAM);
        $this->assertSame('follow_up_team', $team['scope']);
        $this->assertArrayNotHasKey('impact_cells', $team['tables']);
    }

    // ─── Sub-assertion 7 — restore replaces business data ─────────────
    public function test_restore_full_backup_replaces_business_data(): void
    {
        $this->seedFixtures();

        // Capture the seeded state FIRST — the export must see the data it
        // is supposed to restore, not the wiped state.
        $payload = (new BackupService())->export(BackupService::SCOPE_FULL);

        // Mutate the DB so restore visibly replaces it. Use DB::table()
        // (NOT Eloquent delete()) because Guest + ImpactSubmission soft-delete
        // — Eloquent delete() would only stamp deleted_at, leaving rows that
        // still hold RESTRICT FKs and blocking the cell wipe below.
        //
        // Wipe order MUST match the service's reverse-insertion order:
        // users reference impact_cells (users.impact_cell_id RESTRICT) and
        // guests reference both users + cells (RESTRICT), so cells go LAST.
        DB::table('impact_cell_user')->delete();
        DB::table('impact_submissions')->delete();
        DB::table('guests')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('users')->delete();
        DB::table('impact_cells')->delete();
        $this->assertSame(0, Guest::count());
        $this->assertSame(0, User::count());

        (new BackupService())->restore($payload);

        $this->assertSame(2, Guest::count());
        $this->assertSame(1, ImpactCell::count());
        $this->assertSame(1, ImpactSubmission::count());
        $this->assertSame(1, NotificationSetting::count());
        // UUID PKs → don't rely on first() ordering; match by value.
        $this->assertTrue(Guest::where('guest_name', 'Test Data Guest')->exists());
        // Roles came back with the users (full restore = everything).
        $this->assertTrue(DB::table('model_has_roles')->exists());
        $this->assertTrue(User::where('email', 'officer@impact.test')->exists());
    }

    // ─── Sub-assertion 8 — restore rejects non-full archives ──────────
    public function test_restore_rejects_segment_backup(): void
    {
        $segment = (new BackupService())->export(BackupService::SCOPE_IMPACT_CELL);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new BackupService())->restore($segment);
    }

    // ─── Sub-assertion 9 — backup HTTP download streams JSON ──────────
    public function test_backup_route_streams_a_json_download(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->get(route('admin.settings.backup', ['scope' => 'full']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $json = json_decode($response->streamedContent(), true);
        $this->assertSame('full', $json['scope']);
    }

    // ─────────────────────────────────────────────────────────────────
    // Fixtures + helpers
    // ─────────────────────────────────────────────────────────────────

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'name'        => 'Settings ' . $role,
            'active_role' => $role,
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function seedFixtures(): void
    {
        $cell = ImpactCell::create([
            'id'         => (string) Str::uuid(),
            'name'       => 'TEST CELL',
            'is_primary' => true,
            'order'      => 0,
        ]);

        $officer = $this->makeUserWithRole('FollowUpOfficer');
        $officer->update(['email' => 'officer@impact.test']);

        $follower = $this->makeUserWithRole('Impact_Leaders');
        $follower->update(['email' => 'follower@impact.test', 'impact_cell_id' => $cell->id]);

        Guest::create([
            'id'                      => (string) Str::uuid(),
            'guest_name'              => 'Test Data Guest',
            'nearest_impact_cell_id'  => $cell->id,
            'follow_officer_id'       => $officer->id,
        ]);
        Guest::create([
            'id'                 => (string) Str::uuid(),
            'guest_name'         => 'Unassigned Guest',
            'follow_officer_id'  => $officer->id,
        ]);

        ImpactSubmission::create([
            'id'              => (string) Str::uuid(),
            'impact_cell_id'  => $cell->id,
            'user_id'         => $follower->id,
            'type'            => 'report',
            'data'            => ['full_name' => 'Test Submission'],
        ]);

        NotificationSetting::create([
            'action'          => 'WEEKLY_REPORT_SUBMITTED',
            'recipient_email' => 'admin@impact.test',
            'enabled'         => true,
        ]);
    }
}
