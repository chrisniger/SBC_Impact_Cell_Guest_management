<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);

        $query = Activity::with('causer:id,name')->latest();

        if ($actor = $request->get('actor')) {
            $query->where('causer_id', $actor);
        }
        if ($entityType = $request->get('entity')) {
            $query->where('subject_type', 'App\\Models\\' . ucfirst($entityType));
        }
        if ($entityId = $request->get('entity_id')) {
            $query->where('subject_id', $entityId);
        }

        return Inertia::render('Audit/Index', [
            'entries' => $query->limit(500)->get()->map(fn (Activity $a) => [
                'id'          => $a->id,
                'description' => $a->description,
                'actor'       => $a->causer?->name ?? 'System',
                'subjectType' => class_basename($a->subject_type ?? ''),
                'subjectId'   => $a->subject_id,
                'properties'  => $a->properties,
                'createdAt'   => $a->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
