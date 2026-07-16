<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\StoreRoleRequest;
use App\Http\Requests\System\UpdateRoleRequest;
use App\Models\Role;
use App\Services\RoleService;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('system/roles/index', [
            'roles' => $this->roles->overview(),
            'can' => ['create' => Auth::user()->can('create', Role::class)],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = $this->roles->create($request->slug());

        return redirect()
            ->route('system.roles.edit', $role)
            ->with('success', 'Role dibuat. Tetapkan hak aksesnya.');
    }

    public function edit(Role $role): Response
    {
        $this->authorize('view', $role);

        return Inertia::render('system/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->roles->label($role),
                'permissions' => $role->permissions->pluck('name'),
            ],
            'catalogue' => $this->catalogue(),
            // Built-in roles are shown read-only: RolePolicy::update refuses them.
            'editable' => Auth::user()->can('update', $role),
            'can' => ['delete' => Auth::user()->can('delete', $role)],
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->syncPermissions($role, $request->permissions());

        return back()->with('success', 'Hak akses role diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $this->roles->delete($role);

        return redirect()
            ->route('system.roles.index')
            ->with('success', 'Role dihapus.');
    }

    /**
     * The permission catalogue grouped by resource, for the editor's checkboxes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalogue(): array
    {
        $groups = [];

        foreach (PermissionCatalog::resources() as $resource => $actions) {
            $groups[] = [
                'resource' => $resource,
                'permissions' => array_map(
                    fn (string $action): array => [
                        'name' => "{$resource}.{$action}",
                        'action' => $action,
                    ],
                    $actions,
                ),
            ];
        }

        return $groups;
    }
}
