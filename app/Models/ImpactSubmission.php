<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImpactSubmission extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'impact_cell_id',
        'user_id',
        'type',
        'data',
        'fellowship_date_key',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function impactCell(): BelongsTo
    {
        return $this->belongsTo(ImpactCell::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForCell($query, string $cellId)
    {
        return $query->where('impact_cell_id', $cellId);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
