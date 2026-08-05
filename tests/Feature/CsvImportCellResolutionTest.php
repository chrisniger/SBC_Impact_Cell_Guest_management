<?php

namespace Tests\Feature;

use App\Http\Resources\GuestResource;
use App\Models\Guest;
use App\Models\ImpactCell;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CSV import `nearest_impact_cell_id` resolution (2026-08-05).
 *
 * The guests.nearest_impact_cell_id column is a UUID FK to impact_cells.id,
 * but real-world CSVs put the cell NAME ("EFAB WARU") or a cell UUID in
 * that column. The importer must resolve either to the real UUID before
 * writing — a raw name string would 500 the whole batch on the FK
 * constraint (or, without the constraint, be unmappable to a display
 * name later). Unresolvable values skip the row with a clear error,
 * matching the missing-phone / duplicate-phone / invalid-email contract.
 */
class CsvImportCellResolutionTest extends TestCase
{
    private ImpactCell $cell;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->cell = ImpactCell::create([
            'name'       => 'EFAB WARU',
            'is_primary' => true,
            'order'      => 1,
        ]);
    }

    public function test_cell_name_is_resolved_to_uuid_on_import(): void
    {
        $csv = "guest_name,phone,event,impact_status,nearest_impact_cell_id\n"
            . "Name Import,08070000001,Sunday Service,Not Contacted,EFAB WARU\n";

        $this->actingAs($this->admin())->post(route('csv.import.upload'), [
            'csv'      => UploadedFile::fake()->createWithContent('guests.csv', $csv),
            'template' => 'impact',
        ])->assertOk()->assertJson(['created' => 1, 'skipped' => 0, 'errors' => []]);

        $guest = Guest::where('phone', '08070000001')->first();
        $this->assertNotNull($guest);
        $this->assertSame($this->cell->id, $guest->nearest_impact_cell_id);
    }

    public function test_cell_name_match_is_case_insensitive(): void
    {
        $csv = "guest_name,phone,event,impact_status,nearest_impact_cell_id\n"
            . "Lowercase Import,08070000002,Sunday Service,Not Contacted,efab waru\n";

        $this->actingAs($this->admin())->post(route('csv.import.upload'), [
            'csv'      => UploadedFile::fake()->createWithContent('guests.csv', $csv),
            'template' => 'impact',
        ])->assertOk();

        $guest = Guest::where('phone', '08070000002')->first();
        $this->assertNotNull($guest);
        $this->assertSame($this->cell->id, $guest->nearest_impact_cell_id);
    }

    public function test_cell_uuid_passes_through_unchanged(): void
    {
        $csv = "guest_name,phone,event,impact_status,nearest_impact_cell_id\n"
            . "Uuid Import,08070000003,Sunday Service,Not Contacted," . $this->cell->id . "\n";

        $this->actingAs($this->admin())->post(route('csv.import.upload'), [
            'csv'      => UploadedFile::fake()->createWithContent('guests.csv', $csv),
            'template' => 'impact',
        ])->assertOk();

        $guest = Guest::where('phone', '08070000003')->first();
        $this->assertNotNull($guest);
        $this->assertSame($this->cell->id, $guest->nearest_impact_cell_id);
    }

    public function test_unknown_cell_name_skips_row_with_error_detail(): void
    {
        $csv = "guest_name,phone,event,impact_status,nearest_impact_cell_id\n"
            . "Unknown Cell,08070000004,Sunday Service,Not Contacted,NO SUCH CELL\n"
            . "Good Cell,08070000005,Sunday Service,Not Contacted,EFAB WARU\n";

        $response = $this->actingAs($this->admin())->post(route('csv.import.upload'), [
            'csv'      => UploadedFile::fake()->createWithContent('guests.csv', $csv),
            'template' => 'impact',
        ]);

        $response->assertOk();
        $json = $response->json();
        $this->assertSame(1, $json['created']);
        $this->assertSame(1, $json['skipped']);
        $this->assertStringContainsString("unknown impact cell 'NO SUCH CELL'", $json['errors'][0]);

        $this->assertNull(Guest::where('phone', '08070000004')->first());
        $this->assertSame($this->cell->id, Guest::where('phone', '08070000005')->first()->nearest_impact_cell_id);
    }

    public function test_resolved_guest_serializes_with_the_cell_name(): void
    {
        $csv = "guest_name,phone,event,impact_status,nearest_impact_cell_id\n"
            . "Display Check,08070000006,Sunday Service,Not Contacted,EFAB WARU\n";

        $this->actingAs($this->admin())->post(route('csv.import.upload'), [
            'csv'      => UploadedFile::fake()->createWithContent('guests.csv', $csv),
            'template' => 'impact',
        ])->assertOk();

        $guest = Guest::where('phone', '08070000006')->firstOrFail();

        // The wire format the frontend reads (GuestResource) must carry the
        // cell NAME — that is what the guest Show page renders in the
        // Impact Cell card instead of the raw UUID.
        $payload = (new GuestResource($guest))->resolve(app('request'));
        $this->assertSame($this->cell->id, $payload['nearest_impact_cell_id']);
        $this->assertSame('EFAB WARU', $payload['nearest_impact_cell_name']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'name'        => 'CSV Cell Resolution Admin',
            'active_role' => 'Administrator',
        ]);
        $admin->assignRole('Administrator');
        return $admin;
    }

    protected function tearDown(): void
    {
        DB::table('activity_log')->where('log_name', 'csv-import')->delete();
        Guest::whereIn('phone', [
            '08070000001', '08070000002', '08070000003',
            '08070000004', '08070000005', '08070000006',
        ])->forceDelete();
        parent::tearDown();
    }
}
