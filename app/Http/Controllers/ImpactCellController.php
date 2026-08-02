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

        $role = $request->user()?->activeRole();

        $cellsQuery = ImpactCell::with('parent')->ordered();

        // Phase 36 — Impact_Zonal_Coordinator is a READ-ONLY viewer of the
        // Impact Cells surface: they only ever see the cells Admin assigned
        // to them (via the impact_cell_user pivot). Every other role keeps
        // the full network list (Admins manage it; Impact_Leaders read the
        // whole map as part of the outreach grid).
        if ($role === 'Impact_Zonal_Coordinator') {
            $cellsQuery->whereIn('id', $request->user()->zonalImpactCellIds());
        }

        return Inertia::render('ImpactCells/Index', [
            'cells'        => $cellsQuery->get(),
            'hierarchy'    => (bool) $request->input('hierarchy', false),
            'totalCount'   => ImpactCell::count(),
            'primaryCount' => ImpactCell::where('is_primary', true)->count(),
            'subCellCount' => ImpactCell::where('is_primary', false)->count(),
            // Admin chrome gate — without this, the React page's
            // `activeRole` prop arrives undefined and the "Add new cell"
            // button (gated on Administrator after Phase 35) never
            // renders. Matches the create()/show() payloads.
            'activeRole'   => $request->user()?->activeRole(),
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
    public function show(Request $request, string $id): Response
    {
        $cell = ImpactCell::with([
            'parent',
            'subCells' => fn ($q) => $q->orderBy('name'),
            'leaderUsers' => fn ($q) => $q->orderBy('name'),
        ])->findOrFail($id);

        // Phase 36 — defense-in-depth for the zonal read-only scope: the
        // index() list is already filtered to assigned cells, but a zonal
        // must not be able to deep-link into a cell they don't cover.
        if ($request->user()?->activeRole() === 'Impact_Zonal_Coordinator') {
            $isAssigned = $request->user()->zonalImpactCells()->whereKey($cell->id)->exists();
            abort_unless($isAssigned, 403, 'You can only view Impact Cells assigned to you.');
        }

        // Phase 17 — Sub-cells editor payload. Only shown on Show page
        // when the cell is itself primary AND the actor is admin (gated
        // on the React side, but the server check is the source of truth).
        // Filters out (a) self and (b) primaries that already have active
        // sub-cells (the grandparent-trap rule: those can't legally be
        // demoted to a sub-row of THIS primary without violating the
        // 1-level hierarchy). N+1 is acceptable here because the dev-data
        // set is ~69 cells; if seeded data scales up, replace with a
        // single grouped query (sub-cell-count per primary).
        $attachablePrims = [];
        if ($cell->is_primary) {
            $attachablePrims = ImpactCell::primary()
                ->ordered()
                ->where('id', '!=', $id)
                ->get(['id', 'name'])
                ->filter(fn (ImpactCell $c) => ! $c->subCells()->exists())
                ->map(fn (ImpactCell $c) => ['id' => $c->id, 'name' => $c->name])
                ->values()
                ->all();
        }

        return Inertia::render('ImpactCells/Show', [
            'cell'             => $cell,
            'attachablePrims'  => $attachablePrims,
            // Same gate as index() — the Show page's admin-only toggles
            // (Edit details / Edit leadership team / Manage sub-cells) are
            // gated on this prop; without it they silently never appear.
            'activeRole'       => $request->user()?->activeRole(),
            // Phase 32 + Phase 35 — server-computed edit gates (single
            // source of truth: the policy, not the React-side role string).
            // `canEditDetails` = ImpactCellPolicy::update (Administrator
            // only after Phase 35), which owns the cell name + hierarchy —
            // leaders NEVER get it. `canEditLeadership` =
            // ImpactCellPolicy::updateLeadership (Administrator on any
            // cell, or an assigned Impact_Leaders on their OWN cell) —
            // gates the leadership-team editor only.
            'canEditDetails'     => $request->user()?->can('update', $cell) ?? false,
            'canEditLeadership'  => $request->user()?->can('updateLeadership', $cell) ?? false,
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
            // Pass `$cell` as the existing-row so hierarchyRulesOrThrow can
            // reject the Phase 17 "grandparent trap" — demoting a primary
            // that already has children to a sub-cell of another primary
            // would silently produce a 3-level hierarchy.
            ImpactCell::hierarchyRulesOrThrow((bool) $data['is_primary'], $data['parent_cell_id'] ?? null, $cell);
            ImpactCell::ensureParentIsPrimary($data['parent_cell_id'] ?? null);
        } catch (\DomainException $e) {
            return back()->withErrors(['hierarchy' => $e->getMessage()])->withInput();
        }

        // Re-derive child's effective ordering when demoted. Sub-cells keep
        // their `order` but are no longer root anchors — cleanup is left to
        // admins via the Sub-cells editor card.
        $cell->update($data);

        // Phase 32 — redirect BACK to the Show page (not the index list).
        // Root cause of the user's "Details are not saving" report was
        // partly UX: the old redirect landed the admin on the flat index
        // page, so a successful details save was never visibly confirmed
        // on the cell they were editing. Staying on the cell makes the
        // change immediately visible and matches the new leadership
        // endpoint's `back()` behaviour.
        return redirect()->route('impact-cells.show', $cell)
            ->with('success', "Updated cell {$cell->name}.");
    }

    /**
     * PUT /impact-cells/{id}/leadership — leadership-team ONLY write.
     *
     * Phase 32 — split the single fat `update()` PUT into a dedicated
     * leadership endpoint. Root cause of the "leadership team not saving"
     * bug: the React leadership form sent ONLY the 6 free-text leadership
     * fields to /impact-cells/{id}, but `validateCell()` requires `name`
     * + `is_primary`, so every save 303'd back with unreachable validation
     * errors and nothing persisted.
     *
     * This endpoint accepts ONLY the 6 leadership columns (all nullable)
     * and updates ONLY those — the cell name / hierarchy are physically
     * unreachable here, which is what lets an assigned Impact_Leaders edit
     * their own team WITHOUT being able to rename the cell. The full
     * `update()` (name + hierarchy) stays gated behind
     * ImpactCellPolicy::update (Administrator only — Phase 35).
     */
    public function updateLeadership(Request $request, string $id): RedirectResponse
    {
        $cell = ImpactCell::findOrFail($id);
        $this->authorize('updateLeadership', $cell);

        $data = $request->validate(self::leadershipRules());

        $cell->update($data);

        return back()->with('success', "Leadership team for {$cell->name} updated.");
    }

    /**
     * GET /impact-cells/create — Administrator only (via ImpactCellPolicy).
     *
     * Renders the Inertia-driven Create page with the canonical list of
     * primary cells so the Sub-cell parent picker is pre-populated server-
     * side (no extra fetch). `activeRole` passed through for the same
     * reason as Index/Show — admin chrome gates.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', ImpactCell::class);

        return Inertia::render('ImpactCells/Create', [
            'primaries'  => ImpactCell::primary()->ordered()->get(['id', 'name']),
            'activeRole' => $request->user()?->activeRole(),
        ]);
    }

    /**
     * POST /impact-cells/{id}/attach-sub-cell
     *
     * Re-parents a candidate "child" cell so it sits under THIS primary.
     * The child's is_primary flag flips false and parent_cell_id becomes
     * THIS cell's id. Server-side guards:
     *   - child cannot be self (no self-loops)
     *   - child cannot already be a sub-cell of another primary (409)
     *   - parent must authorize update (Administrator)
     *   - child must also authorize update (Administrator) — both rows
     *     change, so both gates must pass
     */
    public function attachSubCell(Request $request, string $id): RedirectResponse
    {
        $parent = ImpactCell::findOrFail($id);
        $this->authorize('update', $parent);

        $data = $request->validate([
            'child_id' => ['required', 'uuid', 'exists:impact_cells,id', 'different:' . $id],
        ]);

        $child = ImpactCell::findOrFail($data['child_id']);
        // Authorize on the child too — both rows change on attach.
        $this->authorize('update', $child);

        if ($child->parent_cell_id !== null) {
            return back()->withErrors([
                'child_id' => "Cell '{$child->name}' is already a sub-cell of another primary. Detach it first.",
            ]);
        }

        // Grandparent trap: if CHILD currently has sub-cells of its own,
        // attaching it as a sub-cell here would silently create a 3-level
        // hierarchy. Block this here too (attachSubCell callers may not be
        // going through validateCell()).
        try {
            ImpactCell::hierarchyRulesOrThrow(false, $parent->id, $child);
            ImpactCell::ensureParentIsPrimary($parent->id);
        } catch (\DomainException $e) {
            return back()->withErrors(['hierarchy' => $e->getMessage()]);
        }

        $child->update([
            'parent_cell_id' => $parent->id,
            'is_primary'     => false,
        ]);

        return back()->with('success', "Attached '{$child->name}' under '{$parent->name}'.");
    }

    /**
     * POST /impact-cells/{id}/detach-sub-cell
     *
     * Promotes an existing child back to a primary cell. The child's
     * parent_cell_id is nulled and is_primary flipped true. Server-side
     * guards:
     *   - child must currently have THIS primary as its parent (409)
     *   - parent + child both authorize update
     *
     * Note: per Phase 17 "fast-action, no modal" — there is NO confirm
     * modal here. If admin accidentally clicks, re-attaching to the same
     * primary is a single subsequent click.
     */
    public function detachSubCell(Request $request, string $id): RedirectResponse
    {
        $parent = ImpactCell::findOrFail($id);
        $this->authorize('update', $parent);

        $data = $request->validate([
            'child_id' => ['required', 'uuid', 'exists:impact_cells,id'],
        ]);

        $child = ImpactCell::findOrFail($data['child_id']);
        $this->authorize('update', $child);

        if ($child->parent_cell_id !== $parent->id) {
            return back()->withErrors([
                'child_id' => "Cell '{$child->name}' is not a sub-cell of '{$parent->name}'.",
            ]);
        }

        $child->update([
            'parent_cell_id' => null,
            'is_primary'     => true,
        ]);

        return back()->with('success', "Promoted '{$child->name}' to a primary cell.");
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
        // Phase 32 — array_merge so the shared leadershipRules() are the
        // single source of truth: a future 7th leadership key automatically
        // lands in BOTH the fat details PUT and the dedicated leadership PUT.
        return $request->validate(array_merge(self::leadershipRules(), [
            'name'                 => ['required', 'string', 'max:255', 'unique:impact_cells,name' . ($existing ? ',' . $existing->id : '')],
            'phone'                => ['nullable', 'string', 'max:32'],
            'address'              => ['nullable', 'string', 'max:255'],
            'parent_cell_id'       => ['required_if:is_primary,false', 'nullable', 'uuid', 'exists:impact_cells,id'],
            'is_primary'           => ['required', 'boolean'],
            'order'                => ['nullable', 'integer', 'min:0'],
        ]));
    }

    /**
     * Phase 32 — shared validation rules for the 6 free-text leadership
     * columns. Single source of truth consumed by BOTH the fat
     * `validateCell()` (details PUT) and the dedicated
     * `updateLeadership()` (leadership PUT) so a max-length tweak can
     * never drift between the two paths.
     *
     * @return array<string, array<int, string>>
     */
    private static function leadershipRules(): array
    {
        return [
            'leader_name'          => ['nullable', 'string', 'max:255'],
            'leader_phone'         => ['nullable', 'string', 'max:32'],
            'assistant_name'       => ['nullable', 'string', 'max:255'],
            'assistant_phone'      => ['nullable', 'string', 'max:32'],
            'welfare_officer_name' => ['nullable', 'string', 'max:255'],
            'welfare_officer_phone'=> ['nullable', 'string', 'max:32'],
        ];
    }
}