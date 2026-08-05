<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\ImpactCell;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 10c — CSV sample downloads + template-column persistence.
 *
 * Locks the user-visible contract added with the sample feature:
 *   1. GET /csv/sample streams a valid CSV (header row + one example row)
 *      for each existing template: default / officer / team / impact.
 *   2. The endpoints are Administrator-only (403 for other roles), and an
 *      unknown template is a 404.
 *   3. Re-importing a downloaded sample creates a guest AND persists the
 *      template-specific columns (contacted_status + visited for officer,
 *      follow_up_status for team, impact_status for impact) — Phase 10's
 *      "rows with Impact Status are saved" acceptance, previously dropped.
 *   4. `visited` CSV strings like 'false' are normalized to a real boolean
 *      (PHP's (bool)'false' would be true, and the raw string 500s on the
 *      tinyint column).
 */
class CsvImportSampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ─── Sub-assertion 1 — each sample streams a header row + example row ─
    public function test_each_template_streams_a_valid_sample_csv(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $cases = [
            ''        => ['guest_name', 'phone', 'email', 'event', 'source'],
            'officer' => ['contacted_status', 'visited'],
            'team'    => ['follow_up_status', 'follow_up_contacts'],
            'impact'  => ['impact_status', 'nearest_impact_cell_id'],
        ];

        foreach ($cases as $template => $extraColumns) {
            $url = $template === ''
                ? route('csv.sample')
                : route('csv.sample', $template);

            $response = $this->actingAs($admin)->get($url);

            $response->assertOk();
            $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

            $csv = $response->streamedContent();
            $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));

            $this->assertCount(2, $lines, "template '{$template}' should have header + one example row");
            $headers = array_map('strtolower', $lines[0]);
            foreach (array_merge(['guest_name', 'phone', 'email', 'event', 'source'], $extraColumns) as $col) {
                $this->assertContains($col, $headers, "template '{$template}' missing header {$col}");
            }
            // Example row must have a phone (the importer's hard requirement).
            $row = array_combine($headers, $lines[1]);
            $this->assertNotEmpty($row['phone'] ?? '', "template '{$template}' example row missing phone");
        }
    }

    // ─── Sub-assertion 2 — admin-only + unknown template 404 ─────────
    public function test_samples_are_admin_only_and_unknown_template_404s(): void
    {
        $leader = $this->makeUserWithRole('Impact_Leaders');
        $this->actingAs($leader)->get(route('csv.sample'))->assertForbidden();

        $admin = $this->makeUserWithRole('Administrator');
        $this->actingAs($admin)->get(route('csv.sample', 'nonsense'))->assertNotFound();
    }

    // ─── Sub-assertion 3 — re-imported samples persist template columns ─
    public function test_import_persists_template_specific_columns(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $cases = [
            // template => [csv header line, example line, asserted guest fields]
            'officer' => [
                'guest_name,phone,email,event,source,contacted_status,visited',
                'Jane Officer,08050000001,jane@example.com,Sunday Service,Welcome Desk,AvailableForVisit,false',
                ['contacted_status' => 'AvailableForVisit', 'visited' => false],
            ],
            'team' => [
                'guest_name,phone,email,event,source,follow_up_status,follow_up_contacts',
                'Jim Team,08050000002,jim@example.com,Sunday Service,Welcome Desk,NOT CONTACTED,[]',
                ['follow_up_status' => 'NOT CONTACTED'],
            ],
            'impact' => [
                'guest_name,phone,email,event,source,impact_status,nearest_impact_cell_id',
                'Ian Impact,08050000003,ian@example.com,Sunday Service,Welcome Desk,Not Contacted,',
                ['impact_status' => 'Not Contacted'],
            ],
        ];

        foreach ($cases as $template => [$headerLine, $exampleLine, $expectedFields]) {
            $csv = $headerLine . "\n" . $exampleLine . "\n";

            $response = $this->actingAs($admin)->post(route('csv.import.upload'), [
                'csv'      => UploadedFile::fake()->createWithContent('guests.csv', $csv),
                'template' => $template,
            ]);

            $response->assertOk();
            $this->assertSame(['created' => 1, 'skipped' => 0, 'errors' => []], $response->json());

            $guest = Guest::where('phone', explode(',', $exampleLine)[1])->first();
            $this->assertNotNull($guest, "template '{$template}' guest not created");
            foreach ($expectedFields as $field => $value) {
                $this->assertSame($value, $guest->{$field}, "template '{$template}' {$field} not persisted");
            }
        }
    }

    // ─── Sub-assertion 5 — per-template export streams sample-style columns ─
    public function test_template_export_streams_sample_columns(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $this->seedExportGuest();

        $cases = [
            'default' => ['guest_name', 'phone', 'email', 'event', 'source'],
            'officer' => ['contacted_status', 'visited'],
            'team'    => ['follow_up_status', 'follow_up_contacts'],
            'impact'  => ['impact_status', 'nearest_impact_cell_id'],
        ];

        foreach ($cases as $template => $extraColumns) {
            $response = $this->actingAs($admin)->get(route('csv.export', ['template' => $template]));

            $response->assertOk();
            $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
            $response->assertHeader('content-disposition', "attachment; filename=\"guests-{$template}.csv\"");

            $csv = $response->streamedContent();
            $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
            $this->assertGreaterThanOrEqual(2, count($lines), "{$template} export should have header + at least the seeded guest");

            $headers = array_map('strtolower', $lines[0]);
            foreach (array_merge(['guest_name', 'phone', 'email', 'event', 'source'], $extraColumns) as $col) {
                $this->assertContains($col, $headers, "{$template} export missing header {$col}");
            }
        }
    }

    // ─── Sub-assertion 6 — export values round-trip through the importer ─
    public function test_template_export_values_are_import_ready(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $this->seedExportGuest();

        // Officer export: visited must serialize as 'true', not '' or a PHP bool.
        $response = $this->actingAs($admin)->get(route('csv.export', ['template' => 'officer']));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('true', $csv);
        $this->assertStringNotContainsString('Array', $csv);

        // Team export: follow_up_contacts (array cast) must serialize as JSON.
        $response = $this->actingAs($admin)->get(route('csv.export', ['template' => 'team']));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('follow_up_contacts', $csv);
        $this->assertStringNotContainsString('Array', $csv);
    }

    // ─── Sub-assertion 7 — template export is admin-only + unknown 404 ─
    public function test_template_export_is_admin_only_and_unknown_404s(): void
    {
        $leader = $this->makeUserWithRole('Impact_Leaders');
        $this->actingAs($leader)->get(route('csv.export', ['template' => 'impact']))->assertForbidden();

        $admin = $this->makeUserWithRole('Administrator');
        $this->actingAs($admin)->get(route('csv.export', ['template' => 'nonsense']))->assertNotFound();
    }

    // ─── Sub-assertion 8 — plain role-based export still works (no template) ─
    public function test_role_based_export_still_works_without_template(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->get(route('csv.export'));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename="guests.csv"');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('guest name', $csv); // humanized header
    }

    // ─── Sub-assertion 9 — sample-import-to-test flow (Phase 10e) ─────
    public function test_sample_content_imports_through_the_regular_pipeline(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        // The impact sample's example row names the real seeded cell
        // 'EFAB WARU' (the importer resolves names → UUIDs), so the test
        // DB must hold that cell for the unedited sample to re-import.
        ImpactCell::create(['name' => 'EFAB WARU', 'is_primary' => true, 'order' => 1]);

        // Replicates the frontend 'Import to test' button: fetch the sample
        // for a template, randomize the example phone (so a fresh guest is
        // always created rather than a duplicate skip), then POST it through
        // the standard /csv/import pipeline with the same template.
        $sampleResponse = $this->actingAs($admin)->get(route('csv.sample', 'impact'));
        $csv = $sampleResponse->streamedContent();
        $csv = preg_replace('/08\d{9}/', '08060000001', $csv, 1);

        $importResponse = $this->actingAs($admin)->post(route('csv.import.upload'), [
            'csv'      => UploadedFile::fake()->createWithContent('sample-test.csv', $csv),
            'template' => 'impact',
        ]);

        $importResponse->assertOk();
        $this->assertSame(['created' => 1, 'skipped' => 0, 'errors' => []], $importResponse->json());
        $guest = Guest::where('phone', '08060000001')->first();
        $this->assertNotNull($guest);
        $this->assertSame('Not Contacted', $guest->impact_status);
    }

    // ─── Sub-assertion 10 — formula-guard apostrophe is stripped on import ─
    public function test_formula_guard_apostrophe_is_stripped_on_import(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        // The export prefixes a lone apostrophe to any value starting with
        // = + - @ (CSV formula-injection guard). Re-importing such a file
        // must strip the guard so the stored cell is the original content,
        // not "'=SUM(A1)". The apostrophe on a plain value ('John) survives.
        $csv = "guest_name,phone,email,event,source,impact_status\n"
            . "'=SUM(A1),08050000005,guard@example.com,'+234 Sunday Service,'@mentions,'=Not Contacted\n";

        $response = $this->actingAs($admin)->post(route('csv.import.upload'), [
            'csv'      => UploadedFile::fake()->createWithContent('guests.csv', $csv),
            'template' => 'impact',
        ]);

        $response->assertOk();
        $this->assertSame(1, $response->json()['created']);

        $guest = Guest::where('phone', '08050000005')->first();
        $this->assertNotNull($guest);
        $this->assertSame('=SUM(A1)', $guest->guest_name);
        $this->assertSame('+234 Sunday Service', $guest->event);
        $this->assertSame('@mentions', $guest->source);
        $this->assertSame('=Not Contacted', $guest->impact_status);
    }

    // ─── Sub-assertion 4 — legacy default template stays base-fields-only ─
    public function test_default_template_import_ignores_unknown_columns(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $csv = "guest_name,phone,email,event,source,impact_status\n"
            . "Plain Guest,08050000004,plain@example.com,Sunday Service,Welcome Desk,Not Contacted\n";

        $response = $this->actingAs($admin)->post(route('csv.import.upload'), [
            'csv'      => UploadedFile::fake()->createWithContent('guests.csv', $csv),
            'template' => '',
        ]);

        $response->assertOk();
        $this->assertSame(1, $response->json()['created']);

        $guest = Guest::where('phone', '08050000004')->first();
        $this->assertNotNull($guest);
        // Without the impact template selected, impact_status must NOT be written.
        $this->assertNull($guest->impact_status);
    }

    // ─── Helpers ─────────────────────────────────────────────────────
    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'name'        => 'CSV Sample ' . $role,
            'active_role' => $role,
        ]);
        $user->assignRole($role);
        return $user;
    }

    /** Create one guest exercising the boolean + array casts for export tests. */
    private function seedExportGuest(): void
    {
        Guest::create([
            'guest_name'         => 'Export Test Guest',
            'phone'              => '08077700001',
            'email'              => 'export@example.com',
            'event'              => 'Sunday Service',
            'source'             => 'Welcome Desk',
            'contacted_status'   => 'AvailableForVisit',
            'visited'            => true,
            'follow_up_status'   => 'NOT CONTACTED',
            'follow_up_contacts' => [],
            'impact_status'      => 'Not Contacted',
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('activity_log')->where('log_name', 'csv-import')->delete();
        Guest::whereIn('phone', ['08077700001', '08060000001', '08050000005'])->forceDelete();
        parent::tearDown();
    }
}
