<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\RoleHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 34 — Admin Roles & Permissions management page.
 *
 * Replaces the Phase 06d.0 "Coming soon" stub with a real editor:
 *
 *   GET    /admin/roles-permissions                → index   (role list + permission catalog)
 *   POST   /admin/roles-permissions                → store   (create role + sync permissions)
 *   PUT    /admin/roles-permissions/{role}         → update  (rename custom role + sync permissions)
 *   DELETE /admin/roles-permissions/{role}         → destroy (delete custom, unassigned role)
 *   POST   /admin/roles-permissions/permissions    → storePermission (add a new permission)
 *
 * Administrator-only (mirrors the other admin controllers). The Inertia
 * route stays behind `gate.stubs` (production-hidden) per the design
 * decision — the write endpoints are NOT gated (same pattern as the
 * Users admin: writes stay available for provisioning, only the listing
 * UI is hidden in production).
 *
 * Guards (deliberate — this page controls the auth model):
 *   - Canonical roles (RoleHelper::ROLE_NAMES) are the single source of
 *     truth that RoleHelper::groupOf() / RoleHelper::canEditField() key
 *     off. Renaming or deleting them would silently break the permission
 *     matrix and every role-based controller gate. Therefore canonical
 *     roles CANNOT be renamed or deleted — only their permissions can be
 *     edited. Custom roles (created here) are fully editable/deletable.
 *   - Deleting a role that still has members is blocked (it would orphan
 *     users' active_role references and cascade away their assignments).
 */
class RolesPermissionsController extends Controller
{
    /**
     * GET /admin/roles-permissions — role list + permission catalog.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id'            => $role->id,
                'name'          => $role->name,
                'guard_name'    => $role->guard_name,
                'is_canonical'  => in_array($role->name, RoleHelper::ROLE_NAMES, true),
                'group'         => RoleHelper::groupOf($role->name),
                'member_count'  => (int) $role->users_count,
                'permissions'   => $role->permissions->pluck('name')->values(),
            ]);

        return Inertia::render('Admin/RolesPermissions/Index', [
            'roles'       => $roles,
            'permissions' => Permission::query()->orderBy('name')->pluck('name')->values(),
            'canonical'   => RoleHelper::ROLE_NAMES,
        ]);
    }

    /**
     * POST /admin/roles-permissions — create a new custom role.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where(fn ($q) => $q->where('guard_name', 'web'))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where(fn ($q) => $q->where('guard_name', 'web'))],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        $this->forgetPermissionCache();

        return back()->with('success', "Role \"{$role->name}\" created.");
    }

    /**
     * PUT /admin/roles-permissions/{role} — rename (custom only) + sync permissions.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $isCanonical = in_array($role->name, RoleHelper::ROLE_NAMES, true);

        $data = $request->validate([
            'name'        => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn ($q) => $q->where('guard_name', 'web'))
                    ->ignore($role->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where(fn ($q) => $q->where('guard_name', 'web'))],
        ]);

        if ($isCanonical) {
            // Canonical role names are locked — only permissions change.
            $role->syncPermissions($data['permissions'] ?? []);
            $this->forgetPermissionCache();

            return back()->with('success', "Permissions updated for \"{$role->name}\".");
        }

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);
        $this->forgetPermissionCache();

        return back()->with('success', "Role \"{$data['name']}\" updated.");
    }

    /**
     * DELETE /admin/roles-permissions/{role} — delete a custom, unassigned role.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if (in_array($role->name, RoleHelper::ROLE_NAMES, true)) {
            throw ValidationException::withMessages([
                'role' => "Canonical role \"{$role->name}\" cannot be deleted — the permission matrix depends on it.",
            ]);
        }

        if ($role->users()->count() > 0) {
            throw ValidationException::withMessages([
                'role' => "Role \"{$role->name}\" still has {$role->users()->count()} member(s). Reassign or remove them first.",
            ]);
        }

        $name = $role->name;
        $role->delete();
        $this->forgetPermissionCache();

        return back()->with('success', "Role \"{$name}\" deleted.");
    }

    /**
     * POST /admin/roles-permissions/permissions — add a new permission to the catalog.
     */
    public function storePermission(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                // dotted snake_case names (matches RoleHelper::PERMISSIONS style)
                'regex:/^[a-z0-9]+(\.[a-z0-9_]+)*$/',
                Rule::unique('permissions', 'name')->where(fn ($q) => $q->where('guard_name', 'web')),
            ],
        ]);

        $permission = Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        // Keep the seeder's baseline contract intact: Administrator holds the
        // full catalog, including permissions added at runtime. Without this,
        // a freshly-created permission would be granted to NO role until an
        // admin remembered to tick it in the edit modal.
        Role::findByName('Administrator', 'web')?->givePermissionTo($permission);
        $this->forgetPermissionCache();

        return back()->with('success', "Permission \"{$permission->name}\" created.");
    }

    // ─────────────────────────────────────────────────────────────────

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);
    }

    /**
     * Spatie caches permissions per-process; every mutation must bust the
     * cache or subsequent `hasPermissionTo()` / gate calls see stale data.
     */
    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
