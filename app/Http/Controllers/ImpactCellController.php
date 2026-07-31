<?php

namespace App\Http\Controllers;

use App\Models\ImpactCell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Impact Cell CRUD.
 *
 * Per Implementation/04_Impact_Cell_Hierarchy.md + HANDOFF.md § 8 #4:
 *   - Hierarchy rules enforced via ImpactCell::hierarchyRulesOrThrow()
 *   - destroy() uses the **pre-check + delete** pattern (NOT try/catch QueryException)
 *     because once Phase 04+ adds ImpactSubmissions.impact_cell_id (or any other
 *     FK referencing impact_cells), a blanket catch would mask those future FK
 *     violations as misleading 409s. The migration's restrictOnDelete() stays as
 *     defense-in-depth for raw SQL deletes.
 *   - Admin-only writes via ImpactCellPolicy (Phase 03 follow-up shipped in
 *     Phase 04 — see app/Policies/ImpactCellPolicy.php).
 *   - index/show render Inertia stubs at resources/js/Pages/ImpactCells/{Index,Show}.tsx;
 *     Phase 04 ships the data layer; the full admin tree UI ships in Phase 08
 *     (Leadership Board), which extends ImpactCells/Index with the rollup view.
 */
class ImpactCellController extends Controller
{
    /**
     * GET /impact-cells — list with optional ?hierarchy=1 to include subCells.
     *
     * Renders the Inertia-driven page at resources/js/Pages/ImpactCells/Index.tsx,
     * which destructures { cells, activeRole }. `cells` is a flat, eager-loaded
     * list (parent names pre-fetched via `with('parent')`) ordered by the
     * ImpactCell::ordered() scope (order asc, then name asc). The legacy
     * totalCount/primaryCount/subCellCount counters are kept on the payload so
     * downstream tools (verifier scripts, future KPI tiles) keep working.
     */
    public function index(Request $request): Response
    {
        $request->validate(['hierarchy' => ['nullable', 'boolean']]);

        return Inertia::render('ImpactCells/Index', [
            'cells'        => ImpactCell::with('parent')->ordered()->get(),
            'hierarchy'    => (bool) $request->input('hierarchy', false),
            'totalCount'   => ImpactCell::count(),
            'primaryCount' => ImpactCell::where('is_primary', true)->count(),
            'subCellCount' => ImpactCell::where('is_primary', false)->count(),
        ]);
    }

    /**
     * GET /impact-cells/{id} — single cell with subCells + parent + leaderUsers.
     * Currently renders a minimal Inertia stub; Phase 04 will flesh out the UI.
     *
     * All three relations are eager-loaded so the React page (ImpactCells/Show.tsx)
     * can render sub-row links (`sub_cells.*`), the breadcrumb back-link
     * (`cell.parent`), AND the assigned-leader roster (`cell.leader_users.*`)
     * without an extra round-trip or N+1.
     *
     * The new `leaderUsers` eager-load was added in Phase 13 alongside the
     * `users.impact_cell_id` FK + the `ImpactCell::leaderUsers()` HasMany
     * relation; without it every Show page would issue a hidden users query
     * per render. The Show page mapping mirrors Laravel's default `toArray()`
     * snake-casing (leaderUsers → leader_users).
     */
    public function show(string $id): Response
    {
        $cell = ImpactCell::with([
            'parent',
            'subCells' => fn ($q) => $q->orderBy('name'),
            'leaderUsers' => fn ($q) => $q->orderBy('name'),
        ])->findOrFail($id);

        return Inertia::render('ImpactCells/Show', [
            'cell' => $cell,
        ]);
    }

    /** POST /impact-cells — Administrator only (via ImpactCellPolicy). */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ImpactCell::class);

        $data = $this->validateCell($request);

        try {
            ImpactCell::hierarchyRulesOrThrow((bool) $data['is_primary'], $data['parent_cell_id'] ?? null);
            ImpactCell::ensureParentIsPrimary($data['parent_cell_id'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['hierarchy' => $e->getMessage()])->withInput();
        }

        ImpactCell::create($data);

        return redirect()->route('impact-cells.index')
            ->with('success', "Created cell {$data['name']}.");
    }

    /** PUT /impact-cells/{id} — Administrator only (via ImpactCellPolicy). */
    public function update(Request $request, string $id): RedirectResponse
    {
        $cell = ImpactCell::findOrFail($id);
        $this->authorize('update', $cell);

        $data = $this->validateCell($request, $cell);

        try {
            ImpactCell::hierarchyRulesOrThrow((bool) $data['is_primary'], $data['parent_cell_id'] ?? null);
            ImpactCell::ensureParentIsPrimary($data['parent_cell_id'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['hierarchy' => $e->getMessage()])->withInput();
        }

        $cell->update($data);

        return redirect()->route('impact-cells.index')
            ->with('success', "Updated cell {$cell->name}.");
    }

    /**
     * DELETE /impact-cells/{id} — Administrator only (via ImpactCellPolicy).
     *
     * Pre-checks (mirrored the HANDOFF§8#4 pattern for raw SQL defense):
     *   - subCells:     abort 409 if any sub-cell points at this primary
     *   - leaderUsers:  abort 409 if any user has this as `impact_cell_id`
     *                   (Phase 13 — mirrors users.impact_cell_id's
     *                   `restrictOnDelete` so an admin gets a friendly
     *                   message instead of a PDOException leaking through
     *                   the controller)
     *
     * The migration's restrictOnDelete() backs this up at the DB level for raw
     * SQL deletes; the controller never catches QueryException (a blanket catch
     * would mask future FK violations as misleading 409s once Phase 04+ adds
     * ImpactSubmissions.impact_cell_id).
     */
    public function destroy(string $id): RedirectResponse
    {
        $cell = ImpactCell::findOrFail($id);
        $this->authorize('delete', $cell);

        if ($cell->subCells()->exists()) {
            abort(409, "Cell '{$cell->name}' has sub-cells. Promote or delete them first.");
        }

        if ($cell->leaderUsers()->exists()) {
            abort(409, "Cell '{$cell->name}' has assigned leaders. Reassign or delete them first.");
        }

        $name = $cell->name;
        $cell->delete();

        return redirect()->route('impact-cells.index')
            ->with('success', "Deleted cell {$name}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCell(Request $request, ?ImpactCell $existing = null): array
    {
        return $request->validate([
            'name'                 => ['required', 'string', 'max:255', 'unique:impact_cells,name' . ($existing ? ',' . $existing->id : '')],
            'phone'                => ['nullable', 'string', 'max:32'],
            'address'              => ['nullable', 'string', 'max:255'],

            // Phase 13 — free-text leadership team columns. Nullable; admin can
            // edit any subset (none / leader only / full team). The 32-char cap
            // on phone mirrors the existing `phone` column and stays short to
            // fit the admin chrome without horizontal scrolling on tablet.
            'leader_name'          => ['nullable', 'string', 'max:255'],
            'leader_phone'         => ['nullable', 'string', 'max:32'],
            'assistant_name'       => ['nullable', 'string', 'max:255'],
            'assistant_phone'      => ['nullable', 'string', 'max:32'],
            'welfare_officer_name' => ['nullable', 'string', 'max:255'],
            'welfare_officer_phone'=> ['nullable', 'string', 'max:32'],

            'parent_cell_id'       => ['nullable', 'uuid', 'exists:impact_cells,id'],
            'is_primary'           => ['required', 'boolean'],
            'order'                => ['nullable', 'integer', 'min:0'],
        ]);
    }
}