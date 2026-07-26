<?php

namespace App\Http\Controllers;

use App\Models\ImpactCell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
     * Currently redirects to dashboard (Phase 03 ships the data layer; Phase 04
     * ships the Inertia UI).
     */
    public function index(Request $request): SymfonyResponse
    {
        $request->validate(['hierarchy' => ['nullable', 'boolean']]);

        return Inertia::render('ImpactCells/Index', [
            'hierarchy'    => (bool) $request->input('hierarchy', false),
            'totalCount'   => ImpactCell::count(),
            'primaryCount' => ImpactCell::where('is_primary', true)->count(),
            'subCellCount' => ImpactCell::where('is_primary', false)->count(),
        ]);
    }

    /**
     * GET /impact-cells/{id} — single cell with subCells.
     * Currently renders a minimal Inertia stub; Phase 04 will flesh out the UI.
     */
    public function show(string $id): SymfonyResponse
    {
        $cell = ImpactCell::with(['subCells' => fn ($q) => $q->orderBy('name')])
            ->findOrFail($id);

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
     * Pre-check: if the cell has sub-cells, abort HTTP 409 with a clear message.
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
            'name'           => ['required', 'string', 'max:255', 'unique:impact_cells,name' . ($existing ? ',' . $existing->id : '')],
            'phone'          => ['nullable', 'string', 'max:32'],
            'address'        => ['nullable', 'string', 'max:255'],
            'parent_cell_id' => ['nullable', 'uuid', 'exists:impact_cells,id'],
            'is_primary'     => ['required', 'boolean'],
            'order'          => ['nullable', 'integer', 'min:0'],
        ]);
    }
}