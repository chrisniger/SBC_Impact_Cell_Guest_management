<?php

namespace Database\Seeders;

use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Phase 16 — `ImpactSubmissionSeeder`.
 *
 * Seed 6 sample submissions (2 members, 1 report, 1 childbirth, 2 souls)
 * attributed to the dev Impact_Leaders user `OweLeader`
 * (`akorchrisowen@gmail.com`, id=7) over the past 3 weeks. Use `?type=`
 * on `/my-reports` to see each kind separately; the dashboard's Recent
 * Submissions panel + the leader variant's KPI cards light up.
 *
 * Why a dedicated seeder?
 *   The codebase has many seeded users (admin, officer, follow-up team,
 *   zonal) but no Impact_Leaders fixture with submissions — every other
 *   Impact_Leaders user signs up via /register which seeds their own data.
 *   For local dev + Playwright/manual QA, having a stable leader with a
 *   pre-populated submission history speeds up dashboard smoke tests
 *   (~30 s instead of 5 min of manual form submissions).
 *
 * Idempotency:
 *   Each sample carries a unique tag in `data->source_seed`. The seeder
 *   queries `ImpactSubmission::where('data->source_seed', $tag)->exists()`
 *   before `create()` so re-running the seeder is a no-op. Real
 *   (non-fixture) submissions to the same account are NEVER touched.
 *
 * Phase 13b-flat awareness:
 *   All cells are primary now (no `parent_cell_id` linkage). Submissions
 *   reference the leader's OWN `impact_cell_id` directly (ACO/JEDO in the
 *   current seed cohort), so the impact-submission's tile math in
 *   `buildBoardData()` and `leaderDashboard()` works out of the box.
 */
class ImpactSubmissionSeeder extends Seeder
{
    /**
     * Source-seed tags — keep stable across releases. If you bump any of
     * these strings, rerun `php artisan migrate:fresh` (the dev workflow)
     * which clears the table and reseeds; don't edit an existing tag.
     */
    private const FIXTURE_PREFIX = 'phase16-dev-fixture';

    public function run(): void
    {
        $user = User::where('email', 'akorchrisowen@gmail.com')
            ->whereHas('roles', fn ($q) => $q->where('name', 'Impact_Leaders'))
            ->first();

        if (! $user) {
            $this->command?->warn('ImpactSubmissionSeeder: OweLeader (Impact_Leaders) not found — skipping. Create via /register first.');
            return;
        }

        $cellId = $user->impact_cell_id;
        if (! $cellId) {
            $this->command?->warn('ImpactSubmissionSeeder: OweLeader has no impact_cell_id (sign them up to ACO/JEDO first) — skipping.');
            return;
        }

        // Sanity check the cell exists in the DB. Without this, a stale
        // `users.impact_cell_id` would FK-violate on `create()` and abort
        // the seed with a stack trace rather than a friendly warning.
        if (! ImpactCell::whereKey($cellId)->exists()) {
            $this->command?->warn("ImpactSubmissionSeeder: impact_cell_id={$cellId} not in impact_cells table — skipping. Run `php artisan db:seed --class=ImpactCellSeeder` first.");
            return;
        }

        $now = now();
        $samples = [
            [
                'slug'                  => 'member-1',
                'type'                  => 'member',
                'created_at_offset_days' => 21,
                'data' => [
                    'full_name' => 'Adaeze Okafor',
                    'phone'     => '+234 800 000 0101',
                    'gender'    => 'female',
                ],
                'fellowship_date_key' => null,
            ],
            [
                'slug'                  => 'member-2',
                'type'                  => 'member',
                'created_at_offset_days' => 14,
                'data' => [
                    'full_name' => 'Tunde Bello',
                    'phone'     => '+234 800 000 0102',
                    'gender'    => 'male',
                ],
                'fellowship_date_key' => null,
            ],
            [
                'slug'                  => 'report-1',
                'type'                  => 'report',
                'created_at_offset_days' => 7,
                'data' => [
                    'adults'          => 12,
                    'children'        => 4,
                    'first_timers'    => 2,
                    'testimonies'     => 'A first-timer gave her life to Christ this week.',
                ],
                'fellowship_date_key' => $now->copy()->subDays(7)->toDateString(),
            ],
            [
                'slug'                  => 'childbirth-1',
                'type'                  => 'childbirth',
                'created_at_offset_days' => 10,
                'data' => [
                    'child_name'   => 'Miracle Adebayo',
                    'mother_name'  => 'Blessing Adebayo',
                    'gender'       => 'female',
                    'father_name'  => 'Tunde Adebayo',
                ],
                'fellowship_date_key' => null,
            ],
            [
                'slug'                  => 'soul-1',
                'type'                  => 'soul',
                'created_at_offset_days' => 5,
                'data' => [
                    'full_name' => 'Saviour Etim',
                    'phone'     => '+234 800 000 0201',
                    'gender'    => 'male',
                    'address'   => 'Plot 12, Garki District, Abuja',
                ],
                'fellowship_date_key' => null,
            ],
            [
                'slug'                  => 'soul-2',
                'type'                  => 'soul',
                'created_at_offset_days' => 2,
                'data' => [
                    'full_name' => 'Favour Johnson',
                    'phone'     => '+234 800 000 0202',
                    'gender'    => 'female',
                    'address'   => 'House 4, Wuse Zone 5, Abuja',
                ],
                'fellowship_date_key' => null,
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($samples as $sample) {
            $tag = self::FIXTURE_PREFIX . '-' . $sample['slug'];
            $createdAt = $now->copy()->subDays($sample['created_at_offset_days']);

            $attributes = array_merge($sample['data'], ['source_seed' => $tag]);

            // Atomic SELECT-or-INSERT via Eloquent's `firstOrCreate`. The
            // unique key is the source_seed tag — Laravel wraps the
            // (`firstOrCreate`) in a single transaction so two concurrent
            // `db:seed` runs cannot double-insert. That's stronger than the
            // exists-then-create pattern (which has a TOCTOU window).
            $submission = ImpactSubmission::firstOrCreate(
                [
                    'user_id'             => $user->id,
                    'impact_cell_id'      => $cellId,
                    'type'                => $sample['type'],
                    'data->source_seed'   => $tag,
                ],
                [
                    'data'                => $attributes,
                    'fellowship_date_key' => $sample['fellowship_date_key'],
                    'created_at'          => $createdAt,
                    'updated_at'          => $createdAt,
                ],
            );

            if ($submission->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->command?->info("ImpactSubmissionSeeder: created {$created}, skipped {$skipped} (idempotent on tag conflict).");
    }
}
