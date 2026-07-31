<?php

namespace Database\Seeders;

use App\Models\ImpactCell;
use Illuminate\Database\Seeder;

/**
 * Seeds the 68 v2 Impact Cell names from Plan/Technical_Documentation/Appendix.md
 * ("Impact Cell Names (70 Hardcoded)"). The spec says ~70; the appendix actually
 * lists 68 — we use what's there. ~70 is approximate.
 *
 * As of 2026-07-31 every seeded cell is primary — `is_primary = true`,
 * `parent_cell_id = NULL`. The dev convenience split (the 4 APO
 * sub-cells) has been removed. The companion forward migration
 * `2026_07_31_120000_flatten_all_impact_cells_to_primary.php` flattens
 * any rows that pre-date this policy.
 *
 * Final state after seed:
 *   - 69 rows total (68 from the appendix + 1 added "APO" so the list has
 *     a symmetric parent reference)
 *   - 69 primary cells (0 children)
 *   - No parent / sub-cell relationships
 *
 * Idempotency contract:
 *   - `firstOrCreate(['name' => …], […])` returns the existing row on
 *     re-seed and does NOT re-apply the defaults — so if an admin
 *     manually changes `is_primary` or `order` on a row, the seeder
 *     will NOT reset it.
 *   - The previously-present Step 2 (APO sub-cell re-parenting) has been
 *     removed. `APO_SUB_CELL_NAMES` is kept empty so external references
 *     (e.g. `scripts/verify_phase03_run.php`) keep type-checking. Verify
 *     Phase 13's flatten migration is documented as the canonical
 *     mechanism for re-flattening if an admin manually creates a sub-cell
 *     in the future.
 */
class ImpactCellSeeder extends Seeder
{
    /**
     * 68 hardcoded v2 cell names from Plan/Technical_Documentation/Appendix.md.
     * Keep this list ordered to match the appendix (mostly alphabetical) so the
     * seeder's `order = $index` produces a deterministic, diff-friendly result.
     *
     * @var list<string>
     */
    private const CELL_NAMES = [
        'ACO/JEDO',
        'ASOKORO',
        'EFAB WARU',
        'APO',                              // Added per Implementation/04 § "Seed strategy" — the split demo needs a parent. NOT in the appendix list.
        'APO MECHANIC',
        'APO LEGISLATIVE QTRS',
        'APO RESETTLEMENT',
        'APO RESETTLEMENT B',
        'APO-DUTSE',
        'BAZE UNIVERSITY ABUJA',
        'BWARI',
        'DAKWO 2 SANTOS ESTATE',
        'DAWAKI',
        'DURUMI 1',
        'DURUMI CELL A: SUCCESS',
        'DURUMI CELL B: JOYFUL',
        'DURUMI CELL C: PEACE',
        'DURUMI CELL D: TESTIMONY',
        'DURUMI 3',
        'GADUWA ESTATE',
        'GALADIMAWA - CELL A',
        'GALADIMAWA VILLAGE',
        'GAMES VILLAGE 1',
        'GARKI AREA 11',
        'GUZAPE',
        'GWAGWALADA',
        'GWAGWALADA CENTER',
        'GWAGWALADA - CHILDREN\'S CHURCH',
        'GWAGWALADA - BY KEYSTONE',
        'GWAGWALADA - KUTUNKU',
        'GWARIMPA',
        'HILLVIEW ESTATE',
        'IDU',
        'JABI',
        'JAHI',
        'JIKWOYI',
        'KABAYI MARARABA 2',
        'KABUSA CENTRE',
        'KABUSA GARDENS',
        'KABUSA1',
        'KADO',
        'KARU',
        'KEFFI',
        'KUBWA',
        'KUBWA CENTER',
        'KUJE',
        'LIFE CAMP 2',
        'LOKOGOMA',
        'LOKOGOMA 2 MINFA 1',
        'LOKOGOMA 4(DONGONGADA)',
        'LUGBE 3 TRADE MOORE',
        'LUGBE ACROSS',
        'LUGBE- CELL B',
        'PYAKASA',
        'RUGA LUGBE',
        'MABUSHI',
        'MANUAL ASSIGNMENT',
        'MASAKA 1',
        'MPAPE',
        'NYANYA FHA',
        'OLYMPIA IMPACT CELL',
        'OUTER GAMES VILLAGE',
        'PORT-HARCOURT',
        'PRINCE & PRINCESS 1',
        'RICHYGOLD HOMES/CEDARCREST',
        'SUNCITY/ GALADIMAWA',
        'WUMBA',
        'WUSE',
        'WUYE 1',
    ];

    /**
     * Empty leaf — kept as `public const` so external references
     * (e.g. `scripts/verify_phase03_run.php`) keep type-checking.
     *
     * Pre-2026-07-31 this list enabled the dev convenience split that
     * re-parented 4 APO children under the primary. That split is no
     * longer part of the seed (the product decision is "every cell is
     * primary; no sub-cells in scope"). The companion forward migration
     * `2026_07_31_120000_flatten_all_impact_cells_to_primary.php`
     * flattens pre-existing data, so the constant staying empty is the
     * single source of truth that the seeder is flat-only.
     *
     * @var list<string>
     */
    public const APO_SUB_CELL_NAMES = [];

    public function run(): void
    {
        // Step 1 (and ONLY step): seed all 69 as primaries, ordered
        // alphabetically. firstOrCreate by name keeps this idempotent on
        // re-seed.
        foreach (self::CELL_NAMES as $i => $name) {
            ImpactCell::firstOrCreate(
                ['name' => $name],
                [
                    'is_primary'     => true,
                    'parent_cell_id' => null,
                    'order'          => $i,
                ],
            );
        }

        // Step 2 (dev-only APO sub-cell split) was removed 2026-07-31.
        // See the class docblock + APO_SUB_CELL_NAMES const above.
    }
}