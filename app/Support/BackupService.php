<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Phase 33 — Backup & Restore service for the Admin Settings page.
 *
 * Exports the application's business data as a structured JSON document
 * (per the "JSON (recommended)" design decision) with four scopes:
 *
 *   - full               → every business table (users, guests, cells,
 *                          submissions, notification rules, pivots).
 *   - impact_cell        → impact cells + submissions + cell-user pivots
 *                          + users holding Impact_* roles + related guests.
 *   - follow_up_officer  → users holding FollowUpOfficer / Follow_UP_Admin
 *                          roles + guests assigned to those officers.
 *   - follow_up_team     → users holding Follow_UP / Follow_UP_View_Only
 *                          roles + the guest rows those teams manage.
 *
 * Restore intentionally accepts ONLY a `full` backup (per the design
 * decision "Restore only from Full backup"): a full restore wipes the
 * business tables and re-inserts the archived rows inside one
 * transaction, so a partial/failed upload never leaves the DB in a
 * half-restored state.
 *
 * Table coverage is the *business* dataset — framework tables (sessions,
 * cache, jobs, password_reset_tokens, migrations) are deliberately
 * excluded because restoring them would corrupt runtime state or collide
 * with migration history.
 *
 * JSON columns (impact_submissions.data, guests.follow_up_contacts) are
 * decoded on export and re-encoded on restore so the archive reads as
 * plain JSON rather than escaped strings.
 */
final class BackupService
{
    public const SCOPE_FULL               = 'full';
    public const SCOPE_IMPACT_CELL        = 'impact_cell';
    public const SCOPE_FOLLOW_UP_OFFICER  = 'follow_up_officer';
    public const SCOPE_FOLLOW_UP_TEAM     = 'follow_up_team';

    public const SCOPES = [
        self::SCOPE_FULL,
        self::SCOPE_IMPACT_CELL,
        self::SCOPE_FOLLOW_UP_OFFICER,
        self::SCOPE_FOLLOW_UP_TEAM,
    ];

    /** Archive format marker + version — bump on schema drift. */
    private const FORMAT = 'impact_portal_backup';
    private const VERSION = 1;

    /**
     * Export the requested scope as an associative array ready for
     * `json_encode()` / streaming download.
     */
    public function export(string $scope): array
    {
        abort_unless(in_array($scope, self::SCOPES, true), 400);

        $payload = [
            'format'      => self::FORMAT,
            'version'     => self::VERSION,
            'scope'       => $scope,
            'exported_at' => now()->toIso8601String(),
            'tables'      => [],
        ];

        switch ($scope) {
            case self::SCOPE_IMPACT_CELL:
                $this->exportImpactCell($payload['tables']);
                break;
            case self::SCOPE_FOLLOW_UP_OFFICER:
                $this->exportFollowUpOfficer($payload['tables']);
                break;
            case self::SCOPE_FOLLOW_UP_TEAM:
                $this->exportFollowUpTeam($payload['tables']);
                break;
            default:
                $this->exportFull($payload['tables']);
        }

        return $payload;
    }

    /**
     * Restore a FULL backup archive. Wipes business tables and
     * re-inserts archived rows inside a single transaction.
     *
     * @throws ValidationException  when the archive is not a full backup.
     * @throws Throwable            re-raised (transaction rolled back).
     */
    public function restore(array $payload): void
    {
        $this->assertValidFullBackup($payload);

        DB::transaction(function () use ($payload) {
            $tables = $payload['tables'];

            // Wipe + re-insert with FK checks OFF: impact_cells has a
            // self-FK (parent_cell_id, restrictOnDelete) and a bulk
            // `DELETE FROM impact_cells` on a DB with sub-cells would
            // otherwise throw a constraint error (children deleted in the
            // same statement as parents). Disabling checks inside the
            // transaction is safe — a rollback still restores everything.
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                // 1) Wipe business tables (FK order: children first).
                foreach (array_reverse($this->insertionOrder()) as $table) {
                    DB::table($table)->delete();
                }

                // 2) Re-insert in FK-safe order.
                foreach ($this->insertionOrder() as $table) {
                    foreach ($tables[$table] ?? [] as $row) {
                        $row = $this->encodeJsonColumns($table, $row);
                        DB::table($table)->insert($row);
                    }
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // Exporters
    // ─────────────────────────────────────────────────────────────────

    private function exportFull(array &$tables): void
    {
        foreach ($this->insertionOrder() as $table) {
            $tables[$table] = $this->rows($table);
        }
    }

    private function exportImpactCell(array &$tables): void
    {
        $tables['impact_cells'] = $this->rows('impact_cells');
        $tables['impact_submissions'] = $this->rows('impact_submissions');
        $tables['impact_cell_user'] = $this->rows('impact_cell_user');

        $impactRoleUsers = $this->userIdsWithRoles([
            'Impact_Leaders',
            'Impact_Cell_Admin',
            'Impact_Cell_Report',
            'Impact_Zonal_Coordinator',
        ]);

        $tables['users'] = $this->usersByIds($impactRoleUsers);
        $tables['model_has_roles'] = $this->roleAssignmentsForUsers($impactRoleUsers);

        $cellIds = collect($tables['impact_cells'])->pluck('id')->all();
        $tables['guests'] = $cellIds
            ? $this->rowsWhereIn('guests', 'nearest_impact_cell_id', $cellIds)
            : [];
    }

    private function exportFollowUpOfficer(array &$tables): void
    {
        $officerUsers = $this->userIdsWithRoles(['FollowUpOfficer', 'Follow_UP_Admin']);

        $tables['users'] = $this->usersByIds($officerUsers);
        $tables['model_has_roles'] = $this->roleAssignmentsForUsers($officerUsers);
        $tables['guests'] = $officerUsers
            ? $this->rowsWhereIn('guests', 'follow_officer_id', $officerUsers)
            : [];
    }

    private function exportFollowUpTeam(array &$tables): void
    {
        $teamUsers = $this->userIdsWithRoles(['Follow_UP', 'Follow_UP_View_Only']);

        $tables['users'] = $this->usersByIds($teamUsers);
        $tables['model_has_roles'] = $this->roleAssignmentsForUsers($teamUsers);
        // Team members manage the SAME guest rows as officers; include the
        // guest records that carry team-owned follow-up fields.
        $tables['guests'] = $this->rows('guests');
    }

    // ─────────────────────────────────────────────────────────────────
    // Row helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * All business tables in FK-safe insert order (restore also uses this).
     *
     * `model_has_roles` (Spatie role assignments) MUST be included — a full
     * restore that brings users back without their roles would leave every
     * account role-less and break the permission model. It sits after
     * `users` (inserted after users exist; wiped before them on restore).
     */
    private function insertionOrder(): array
    {
        return [
            'impact_cells',
            'users',
            'model_has_roles',
            'impact_cell_user',
            'guests',
            'impact_submissions',
            'notification_settings',
        ];
    }

    /**
     * Full-row dump for a table, decoding JSON columns.
     *
     * NO `orderBy('id')` — the impact_cell_user pivot has a composite PK
     * (user_id, impact_cell_id) and no `id` column, so ordering by `id`
     * throws a SQLSTATE 42S22 on MySQL. A stable insertion order is not
     * needed for a backup archive; callers must never depend on row order.
     */
    private function rows(string $table): array
    {
        return DB::table($table)->get()
            ->map(fn ($row) => $this->decodeJsonColumns($table, (array) $row))
            ->values()
            ->all();
    }

    private function rowsWhereIn(string $table, string $column, array $ids): array
    {
        return DB::table($table)->whereIn($column, $ids)->get()
            ->map(fn ($row) => $this->decodeJsonColumns($table, (array) $row))
            ->values()
            ->all();
    }

    private function usersByIds(array $ids): array
    {
        return $ids
            ? $this->rowsWhereIn('users', 'id', $ids)
            : [];
    }

    /**
     * Users who hold any of the given Spatie role names.
     */
    private function userIdsWithRoles(array $roleNames): array
    {
        if (empty($roleNames)) {
            return [];
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', $roleNames)
            ->pluck('model_has_roles.model_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * model_has_roles rows restricted to a set of user ids.
     * `model_id` is a string in the pivot (morph), so compare as strings.
     */
    private function roleAssignmentsForUsers(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return DB::table('model_has_roles')
            ->whereIn('model_id', $userIds)
            ->orderBy('model_id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->values()
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────
    // JSON column (de)serialisation
    // ─────────────────────────────────────────────────────────────────

    private function decodeJsonColumns(string $table, array $row): array
    {
        foreach ($this->jsonColumns($table) as $col) {
            if (isset($row[$col]) && is_string($row[$col]) && $row[$col] !== '') {
                $decoded = json_decode($row[$col], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$col] = $decoded;
                }
            }
        }

        return $row;
    }

    private function encodeJsonColumns(string $table, array $row): array
    {
        foreach ($this->jsonColumns($table) as $col) {
            if (isset($row[$col]) && is_array($row[$col])) {
                $row[$col] = json_encode($row[$col]);
            }
        }

        return $row;
    }

    private function jsonColumns(string $table): array
    {
        return match ($table) {
            'impact_submissions' => ['data'],
            'guests'             => ['follow_up_contacts'],
            default              => [],
        };
    }

    // ─────────────────────────────────────────────────────────────────
    // Restore validation
    // ─────────────────────────────────────────────────────────────────

    private function assertValidFullBackup(array $payload): void
    {
        if (($payload['format'] ?? null) !== self::FORMAT) {
            throw ValidationException::withMessages([
                'backup_file' => 'This file is not a valid Impact Portal backup archive.',
            ]);
        }

        if ((int) ($payload['version'] ?? 0) !== self::VERSION) {
            throw ValidationException::withMessages([
                'backup_file' => "Unsupported backup version. Expected v" . self::VERSION . '.',
            ]);
        }

        if (($payload['scope'] ?? null) !== self::SCOPE_FULL) {
            throw ValidationException::withMessages([
                'backup_file' => 'Only a FULL backup can be restored. Download a full backup and try again.',
            ]);
        }

        $tables = (array) ($payload['tables'] ?? []);
        foreach ($this->insertionOrder() as $table) {
            if (! array_key_exists($table, $tables)) {
                throw ValidationException::withMessages([
                    'backup_file' => "Backup archive is missing table: {$table}.",
                ]);
            }
        }
    }
}
