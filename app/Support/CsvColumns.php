<?php

namespace App\Support;

/**
 * Single source of truth for CSV import/export column sets (Phase 10b).
 *
 * Two responsibilities:
 *  - `forRole($role)`: returns the column list for CSV EXPORT per role.
 *    Administrator sees all group-owned columns; non-Admin sees a subset
 *    (per Phase 10's 14-col / 8-col split).
 *  - `aliasesForTemplate($template)`: returns the per-template alias map
 *    used during CSV IMPORT to normalise header-row string variations.
 *    Templates: officer | team | impact (per CsvImportController validator).
 *
 * Lives SEPARATELY from RoleHelper because column-policy intent (who may
 * WRITE which guest column) is distinct from CSV wire-format intent (what
 * becomes a header row in the export / what alias-maps during the import).
 * Both reference DATABASE column names (snake_case) so they stay aligned
 * with `guests` migration + GuestResource output.
 */
final class CsvColumns
{
    /**
     * EXPORT column-set for the given role (snake_case DB column names).
     * Mirrored from Phase 10's CsvExportController::columnsForRole(); this
     * helper replaces the inline private method as of Phase 10b.
     */
    public static function forRole(?string $role): array
    {
        if ($role === 'Administrator') {
            return [
                'guest_name', 'phone', 'email', 'address', 'event', 'event_other', 'source',
                'contacted_status', 'visited',
                'follow_up_status', 'follow_up_contacts',
                'nearest_impact_cell_id', 'follow_officer_id', 'created_at',
            ];
        }

        // Officer + Team subset (group-owned columns excluded).
        return [
            'guest_name', 'phone', 'email', 'event', 'source',
            'contacted_status', 'visited', 'created_at',
        ];
    }

    /**
     * IMPORT alias-map per template (header-row string → canonical snake_case field).
     * The "base" set is group-agnostic (always imported regardless of template);
     * the per-template branches add field-specific aliases that match each
     * user group's column policy per Implementation/03 § Column Policy.
     */
    public static function aliasesForTemplate(?string $template): array
    {
        $base = [
            'phone'      => ['phone', 'phone number', 'mobile', 'tel', 'telephone'],
            'guest_name' => ['name', 'guest name', 'full name', 'guest_name', 'fullname'],
            'email'      => ['email', 'e-mail', 'email address'],
            'event'      => ['event', 'service', 'meeting'],
            'source'     => ['source', 'referral', 'how they heard'],
        ];

        return match ($template) {
            // Officer template: surface officer-group-owned columns.
            'officer' => $base + [
                'contacted_status' => ['contacted status', 'contacted_status', 'contact'],
                'visited'          => ['visited', 'has visited'],
            ],
            // Team template: surface team-group-owned columns.
            'team' => $base + [
                'follow_up_status'   => ['follow up status', 'follow_up_status', 'followup'],
                'follow_up_contacts' => ['follow up contacts', 'follow_up_contacts', 'contacts json', 'contacts_json'],
            ],
            // Impact template: surface impact-group-owned columns.
            'impact' => $base + [
                'impact_status'          => ['impact status', 'impact_status', 'cell'],
                'nearest_impact_cell_id' => ['cell id', 'cell_id', 'nearest impact cell'],
            ],
            // Unknown / null template: base only.
            default => $base,
        };
    }

    /**
     * SAMPLE header-row columns per import template (Phase 10c).
     *
     * Used by CsvImportController::sample() to generate a downloadable sample
     * CSV per existing CSV system (default / officer / team / impact). Headers
     * are the canonical snake_case column names — every one doubles as a valid
     * header alias inside `aliasesForTemplate`, so a downloaded sample
     * re-imports cleanly even completely unedited. `guest_name` leads for
     * human readability; the template branches mirror `aliasesForTemplate`.
     */
    public static function sampleColumnsForTemplate(?string $template): array
    {
        $base = ['guest_name', 'phone', 'email', 'event', 'source'];

        return match ($template) {
            'officer' => [...$base, 'contacted_status', 'visited'],
            'team'    => [...$base, 'follow_up_status', 'follow_up_contacts'],
            'impact'  => [...$base, 'impact_status', 'nearest_impact_cell_id'],
            default   => $base,
        };
    }
}
