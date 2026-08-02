<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 34 — in-app announcement (the real /admin/messages page).
 *
 * One row per announcement posted by an Administrator from the Messages
 * board. `author` may be null (nullOnDelete FK) — the UI falls back to
 * a generic "Administrator" label in that case.
 */
class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'author_user_id'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
