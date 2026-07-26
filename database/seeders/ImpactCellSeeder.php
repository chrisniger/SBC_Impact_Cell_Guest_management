<?php

namespace Database\Seeders;

use App\Models\ImpactCell;
use Illuminate\Database\Seeder;

/**
 * Seeds the 68 v2 Impact Cell names from Plan/Technical_Documentation/Appendix.md
 * ("Impact Cell Names (70 Hardcoded)"). The spec says ~70; the appendix actually
 * lists 68 — we use what's there. ~70 is approximate.
 *
 * Hierarchy per Implementation/04_Impact_Cell_Hierarchy.md § "Seed strategy":
 *   1. All cells seeded as primary (`is_primary = true`, `parent_cell_id = NULL`),
 *      ordered alphabetically.
 *   2. After the initial seed, the dev convenience split re-parents 4 of the APO*
 *      cells (APO-DUTSE, APO RESETTLEMENT, APO RESETTLEMENT B, APO LEGISLATIVE QTRS)
 *      into sub-cells under the APO primary — turning them from primaries into
 *      non-primaries. APO itself stays primary.
 *
 * Final state after seed:
 *   - 69 rows total (68 from the appendix + 1 added "APO" so the split demo has a parent)
 *   - 65 primary cells (69 - 4 re-parented; APO is still primary, plus 64 others)
 *   - 4 children (APO-DUTSE, APO RESETTLEMENT, APO RESETTLEMENT B, APO LEGISLATIVE QTRS)
 *   - 1 of those primaries (APO) has children
 *
 * Idempotency contract (intentional asymmetry, do NOT silently fix without thinking):
 *   - `firstOrCreate(['name' => …], […])` on Step 1 returns the existing row
 *     without re-applying the defaults — so if an admin manually changes
 *     `is_primary` or `order` on a non-APO cell, the seeder will NOT reset it.
 *   - Step 2 re-applies the APO split on every run — if an admin manually
 *     promotes one of the 4 APO children back to primary, the seeder will
 *     silently re-parent it. This is intentional for the dev split demo but
 *     should NOT ship to prod (per Implementation/04 § "Seed strategy":
 *     "The prod seed should NOT create these dev-only sub-cell links").
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
     * The 4 sub-cells of the APO primary (per Implementation/04 § "Seed strategy").
     * After the initial seed these rows are re-parented (parent_cell_id = APO's id,
     * is_primary = false) — converting them from primaries into sub-cells.
     *
     * Exposed as `public const` so scripts/verify_phase03_run.php can reference it
     * for the destructive [7] assertion (reset + re-seed test).
     *
     * @var list<string>
     */
    public const APO_SUB_CELL_NAMES = [
        'APO-DUTSE',
        'APO RESETTLEMENT',
        'APO RESETTLEMENT B',
        'APO LEGISLATIVE QTRS',
    ];

    public function run(): void
    {
        // Step 1: seed all 68 as primaries, ordered alphabetically.
        // firstOrCreate by name keeps this idempotent on re-seed.
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

        // Step 2: split the APO primary — re-parent the 4 APO sub-cells under APO.
        $apo = ImpactCell::where('name', 'APO')->first();
        if ($apo !== null) {
            foreach (self::APO_SUB_CELL_NAMES as $subName) {
                $sub = ImpactCell::where('name', $subName)->first();
                if ($sub === null) {
                    continue;   // name not present in the list — defensive
                }
                // Idempotent: only update if the row isn't already a child of APO.
                if ($sub->parent_cell_id !== $apo->id || $sub->is_primary !== false) {
                    $sub->update([
                        'parent_cell_id' => $apo->id,
                        'is_primary'     => false,
                    ]);
                }
            }
        }
    }
}