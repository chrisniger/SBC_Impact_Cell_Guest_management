<?php

namespace App\Http\Controllers;

use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use App\Models\NotificationSetting;
use App\Support\RoleHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ImpactSubmissionController extends Controller
{
    private const TYPES = ['member', 'report', 'childbirth', 'soul'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user?->activeRole();
        $group = RoleHelper::groupOf($role);

        $query = ImpactSubmission::with(['impactCell:id,name', 'user:id,name'])
            ->orderByDesc('created_at');

        if ($role === 'Impact_Zonal_Coordinator') {
            // Phase 36 — Zonal Coordinators are read-only viewers: they see
            // the full activity feed for the cells assigned to them (they
            // cannot submit, so an own-submissions filter would be empty).
            $query->whereIn('impact_cell_id', $user->zonalImpactCellIds());
        } elseif (RoleHelper::isImpactCellAdmin($role)) {
            // Phase 09 — cross-cell supervisor scope: every submission by any
            // user whose active_role ∈ GROUP_IMPACT_CELL (leaders, cell admins,
            // cell report, zonal coordinator).
            $query->whereHas('user', fn ($q) => $q->whereIn('active_role', RoleHelper::GROUP_IMPACT_CELL));
        } elseif ($group === 'impactCell') {
            $query->forUser($user->id);
        }

        $submissions = $query->paginate(20);

        return Inertia::render('ImpactSubmissions/Index', [
            'submissions' => $submissions,
            'activeRole'  => $role,
            // Phase 36 — zonals cannot create submissions (read-only).
            'canCreate'   => $role !== 'Impact_Zonal_Coordinator'
                && ($role === 'Administrator' || $group === 'impactCell'),
        ]);
    }

    public function create(Request $request): Response
    {
        // Phase 36 — zonals cannot submit reports (read-only role).
        abort_if(
            $request->user()?->activeRole() === 'Impact_Zonal_Coordinator',
            403,
            'Zonal Coordinators can view cell activity but cannot submit reports.',
        );

        $type = $request->get('type', 'member');
        if (! in_array($type, self::TYPES, true)) $type = 'member';

        $cells = ImpactCell::select('id', 'name')->orderBy('name')->get();
        $user = $request->user();
        $activeRole = $user?->activeRole();
        $assignedCell = $activeRole === 'Impact_Leaders'
            ? $user?->assignedImpactCell
            : null;

        return Inertia::render('ImpactSubmissions/Create', [
            'type'         => $type,
            'cells'        => $cells,
            'activeRole'   => $activeRole,
            // Impact_Leaders are bound to the cell chosen at registration.
            // The report form renders this as read-only instead of offering
            // a second, potentially conflicting cell selector.
            'assignedCell' => $assignedCell?->only(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $role = $user?->activeRole();
        $group = RoleHelper::groupOf($role);

        // Phase 36 — Impact_Zonal_Coordinator sits inside the impactCell
        // group but is read-only: it may view activity, never submit.
        if ($role === 'Impact_Zonal_Coordinator') {
            abort(403, 'Zonal Coordinators can view cell activity but cannot submit reports.');
        }

        if ($role !== 'Administrator' && $group !== 'impactCell') {
            abort(403, 'Only Impact Cell leaders can submit.');
        }

        // Every submission made by an Impact Leader is attributed to the
        // cell assigned during registration. Ignore any client-provided
        // value so the UI rule is also enforced for hand-crafted requests.
        if ($role === 'Impact_Leaders') {
            $request->merge(['impact_cell_id' => $user->impact_cell_id]);
        }

        $validated = $request->validate([
            'impact_cell_id'     => ['required', 'uuid', 'exists:impact_cells,id'],
            'type'               => ['required', 'string', 'in:' . implode(',', self::TYPES)],
            'data'               => ['required'],
            'fellowship_date_key' => ['nullable', 'string', 'max:64'],
            'receipt'            => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:10240'],
        ]);

        // Decode JSON data — the frontend sends `data` as a JSON string when
        // using FormData (multipart form for file uploads), or as a native
        // array when no file is attached (standard POST).
        $data = $validated['data'];
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        if (! is_array($data)) {
            $data = [];
        }

        // Store receipt file if uploaded
        if ($request->hasFile('receipt')) {
            $path = $request->file('receipt')->store('receipts', 'public');
            $data['receipt_path'] = $path;
        }

        if ($validated['type'] === 'report' && !empty($validated['fellowship_date_key'])) {
            $existing = ImpactSubmission::where('impact_cell_id', $validated['impact_cell_id'])
                ->where('fellowship_date_key', $validated['fellowship_date_key'])
                ->where('type', 'report')
                ->exists();

            if ($existing) {
                return back()->withErrors([
                    'fellowship_date_key' => 'A report for this cell and date already exists.',
                ]);
            }
        }

        $submission = ImpactSubmission::create([
            'impact_cell_id'     => $validated['impact_cell_id'],
            'user_id'            => $user->id,
            'type'               => $validated['type'],
            'data'               => $data,
            'fellowship_date_key' => $validated['fellowship_date_key'] ?? null,
        ]);

        // Phase 09 — trigger notification for weekly reports.
        if ($validated['type'] === 'report') {
            $this->notifyReportSubmitted($submission);
        }

        return redirect()
            ->route('impact-submissions.show', $submission->id)
            ->with('success', ucfirst($validated['type']) . ' submission created.');
    }

    public function show(Request $request, string $id): Response
    {
        $submission = ImpactSubmission::with(['impactCell:id,name', 'user:id,name'])->findOrFail($id);

        // Phase 36 — defense-in-depth for the zonal read-only scope: the
        // index() list is filtered to assigned cells, but the detail page
        // must not be deep-linkable for a cell the zonal doesn't cover.
        if ($request->user()?->activeRole() === 'Impact_Zonal_Coordinator') {
            $isAssigned = in_array(
                $submission->impact_cell_id,
                $request->user()->zonalImpactCellIds(),
                true,
            );
            abort_unless($isAssigned, 403, 'You can only view activity for Impact Cells assigned to you.');
        }

        return Inertia::render('ImpactSubmissions/Show', [
            'submission' => $submission,
        ]);
    }

    public function soulSearch(Request $request): Response
    {
        return Inertia::render('ImpactSubmissions/SoulSearch', [
            'activeRole' => $request->user()?->activeRole(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $query = ImpactSubmission::where('type', 'soul')
            ->where('data', 'like', "%{$q}%")
            ->with(['impactCell:id,name'])
            ->orderByDesc('created_at');

        // Phase 36 — zonal coordinators search only within their assigned
        // cells (Soul Search surfaces cell-activity data).
        if ($request->user()?->activeRole() === 'Impact_Zonal_Coordinator') {
            $query->whereIn('impact_cell_id', $request->user()->zonalImpactCellIds());
        }

        $results = $query->limit(20)->get()->map(fn ($s) => [
                'id'           => $s->id,
                'name'         => $s->data['full_name'] ?? $s->data['name'] ?? null,
                'phone'        => $s->data['phone'] ?? null,
                'gender'       => $s->data['gender'] ?? null,
                'cell'         => $s->impactCell?->name,
                'created_at'   => $s->created_at?->toIso8601String(),
            ]);

        return response()->json($results);
    }

    private function notifyReportSubmitted(ImpactSubmission $submission): void
    {
        $rules = NotificationSetting::where('action', 'WEEKLY_REPORT_SUBMITTED')
            ->where('enabled', true)
            ->get();

        if ($rules->isEmpty()) {
            Log::info('WEEKLY_REPORT_SUBMITTED: no rules configured, skipped.');
            return;
        }

        $cellName = $submission->impactCell?->name ?? 'Unknown Cell';
        $submitterName = $submission->user?->name ?? 'Unknown';

        foreach ($rules as $rule) {
            try {
                Mail::raw(
                    "A weekly report was submitted for {$cellName} by {$submitterName}.\n\n"
                    . "Fellowship date: {$submission->fellowship_date_key}\n"
                    . "View at: " . route('impact-submissions.show', $submission->id),
                    function ($message) use ($rule) {
                        $message->to($rule->recipient_email)
                            ->subject('Weekly Report Submitted');
                    }
                );
            } catch (\Exception $e) {
                Log::warning("Failed to send WEEKLY_REPORT_SUBMITTED notification to {$rule->recipient_email}: {$e->getMessage()}");
            }
        }
    }

    public function mySubmissions(Request $request): Response
    {
        $user = $request->user();
        $role = $user?->activeRole();
        $group = RoleHelper::groupOf($role);

        // Phase 16 — chip filter via `?type=` query, validated against self::TYPES.
        // Invalid values silently fall back to "all" so a hand-crafted URL like
        // `?type=banana` doesn't 500; the React chip row reads `activeType` to
        // decide which chip is highlighted. `: null` (after the in_array check)
        // means "All" is selected — the row dropdown stays valid for that case.
        $requestedType = (string) $request->query('type', '');
        $activeType = in_array($requestedType, self::TYPES, true) ? $requestedType : null;

        $query = ImpactSubmission::with(['impactCell:id,name'])
            ->orderByDesc('created_at');

        if ($role === 'Impact_Zonal_Coordinator') {
            // Phase 36 — zonal coordinators view all activity for their
            // assigned cells (they have no own submissions to show).
            $query->whereIn('impact_cell_id', $user->zonalImpactCellIds());
        } elseif (RoleHelper::isImpactCellAdmin($role)) {
            // Cross-cell admin scope: every submission by any user whose
            // active_role ∈ GROUP_IMPACT_CELL.
            $query->whereHas('user', fn ($q) => $q->whereIn('active_role', RoleHelper::GROUP_IMPACT_CELL));
        } elseif ($group === 'impactCell') {
            // Per-user scope (own submissions only).
            $query->forUser($user->id);
        }

        if ($activeType !== null) {
            // Phase 16 — narrow to the chip type. scopeOfType is defined on
            // the model (`app/Models/ImpactSubmission.php` line 40).
            $query->ofType($activeType);
        }

        return Inertia::render('ImpactSubmissions/MySubmissions', [
            // Phase 16 — prop renamed `reports` → `submissions`. The query
            // pagination preserves the active `?type=` via `withQueryString()`
            // so chip+page round-trips cleanly across paginated URLs.
            'submissions' => $query->paginate(20)->withQueryString(),
            'activeRole'  => $role,
            'activeType'  => $activeType,
        ]);
    }
}
