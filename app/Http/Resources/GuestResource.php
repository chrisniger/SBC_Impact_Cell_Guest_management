<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Guest API Resource — Phase 04 output masking.
 *
 * Per Implementation/03 "Hidden from non-admins" + bridge § 6:
 *   - `deleted_at`, `updated_at`, `created_at` are admin-only raw timestamps
 *     (the spec says "Hidden from non-admins: deletedAt, updatedAt raw
 *     timestamps — frontend shows human-friendly dates only"). For non-admin
 *     viewers these are returned as `null` so the frontend does not have to
 *     choose between "show ISO 8601 to non-admin" vs "hide it".
 *   - All other fields are returned as-is — the column-level READ decision
 *     is intentionally NOT replicated here (per Implementation/03 § 4 the
 *     matrix says "V" for almost every column for every group, so the
 *     resource is "view-mostly"). The Form Request's `prepareForValidation()`
 *     is the single source of truth for the WRITE decision.
 *
 * Dates are formatted as ISO 8601 for the frontend (`Date.parse`-friendly).
 */
class GuestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user !== null && $user->activeRole() === 'Administrator';

        return [
            'id' => $this->id,

            // Core record
            'date'        => $this->date?->toIso8601String(),
            'event'       => $this->event,
            'event_other' => $this->event_other,
            'guest_name'  => $this->guest_name,
            'source'      => $this->source,

            // Demographics
            'gender'         => $this->gender,
            'marital_status' => $this->marital_status,
            'age'            => $this->age,
            'phone'          => $this->phone,
            'address'        => $this->address,

            // Impact Cell group columns
            'nearest_impact_cell_id' => $this->nearest_impact_cell_id,
            'impact_status'          => $this->impact_status,

            // Follow Up Officer group columns
            'contacted_status'  => $this->contacted_status,
            'join_when'         => $this->join_when,
            'days_available'    => $this->days_available,
            'comments'          => $this->comments,
            'visited'           => $this->visited,
            'visited_at'        => $this->visited_at,
            'indicated_to_join' => $this->indicated_to_join,
            'visitation_status' => $this->visitation_status,
            'feedback'          => $this->feedback,

            // Follow Up Team group columns
            'follow_up_status'   => $this->follow_up_status,
            'follow_up_contacts' => $this->follow_up_contacts,

            // Assignment
            'follow_officer_id' => $this->follow_officer_id,

            // Meta — admin-only raw timestamps (hidden from non-admin per spec)
            'created_at' => $isAdmin ? $this->created_at?->toIso8601String() : null,
            'updated_at' => $isAdmin ? $this->updated_at?->toIso8601String() : null,
            'deleted_at' => $isAdmin ? $this->deleted_at?->toIso8601String() : null,
        ];
    }
}
