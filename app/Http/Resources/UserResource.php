<?php

namespace App\Http\Resources;

use App\Support\RoleHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Phase 06e+1 — UserResource.
 *
 * Trims a full `User` model down to the columns the Admin/Users page
 * actually needs:
 *
 *   id, name, email, email_verified_at,
 *   roles[] (Spatie), active_role,
 *   // Phase 02-derived helpers:
 *   active_group (derived from active_role via RoleHelper),
 *   // Phase 06e+1 helpers:
 *   has_multiple_roles (UX hint for the +N badge on the table),
 *   last_seen_at (real last activity; updated by RecordLastSeen middleware),
 *   joined_at (created_at — convenience for UX labels),
 *
 * Password + remember_token + email_verified_at verification URL are
 * NEVER exposed — they're either sensitive or surface to admins in
 * places we don't want (e.g. audit log screen).
 *
 * Output is unconditional (no role-based masking) because the only
 * consumer is `/admin/users`, which is Administrator-only (UserPolicy).
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roles = $this->resource->getRoleNames()->all();
        $activeRole = $this->resource->activeRole();

        return [
            'id'                => (int) $this->resource->id,
            'name'              => (string) $this->resource->name,
            'email'             => (string) $this->resource->email,
            'email_verified_at' => optional($this->resource->email_verified_at)?->toIso8601String(),

            // Spatie role graph
            'roles'             => $roles,
            'has_multiple_roles'=> count($roles) > 1,

            // Active-role selector (Phase 02)
            'active_role'       => $activeRole,
            'active_group'      => $activeRole !== null ? RoleHelper::groupOf($activeRole) : null,

            // Activity timestamps — both used by the Admin/Users table.
            'last_seen_at'      => optional($this->resource->last_seen_at)?->toIso8601String(),
            'joined_at'         => optional($this->resource->created_at)?->toIso8601String(),

            // Phase 06e+3 — soft-delete state. Always included so the
            // Index page and Edit page can render banner / filter UI off
            // the same shape; `trashed` is the boolean short-circuit.
            'deleted_at'        => optional($this->resource->deleted_at)?->toIso8601String(),
            'trashed'           => $this->resource->trashed(),
        ];
    }
}
