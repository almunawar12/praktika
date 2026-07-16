<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\DataTransferObjects\TableRequest;
use App\DataTransferObjects\UserData;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\StoreUserRequest;
use App\Http\Requests\System\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $table = TableRequest::fromRequest($request, ['role', 'status']);

        return Inertia::render('system/users/index', [
            'users' => $this->users->paginate($table)->through(fn (User $user): array => $this->row($user)),
            'table' => $table->toArray(),
            'roles' => $this->roleOptions(RoleName::cases()),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('system/users/create', [
            'roles' => $this->roleOptions(UserService::ASSIGNABLE),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create(new UserData(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            phone: $request->filled('phone') ? $request->string('phone')->toString() : null,
            isActive: $request->boolean('is_active'),
            role: $request->enum('role', RoleName::class),
            password: $request->string('password')->toString(),
        ));

        return redirect()
            ->route('system.users.edit', $user)
            ->with('success', 'Pengguna dibuat.');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        $actor = $request->user();

        return Inertia::render('system/users/edit', [
            'user' => [
                ...$this->row($user),
                'phone' => $user->phone,
            ],
            'roles' => $this->roleOptions(UserService::ASSIGNABLE),
            'hasProfile' => $this->users->hasProfile($user),
            'can' => [
                'delete' => $actor->can('delete', $user),
                'toggleStatus' => $actor->can('toggleStatus', $user),
                'resetPassword' => $actor->can('resetPassword', $user),
                'impersonate' => $actor->can('impersonate', $user),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, new UserData(
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            phone: $request->filled('phone') ? $request->string('phone')->toString() : null,
            isActive: $request->boolean('is_active'),
            role: $request->filled('role') ? $request->enum('role', RoleName::class) : null,
            password: null,
        ));

        return back()->with('success', 'Pengguna diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->users->delete($user);

        return redirect()
            ->route('system.users.index')
            ->with('success', 'Pengguna dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        $role = $user->roleName();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, RoleName>  $roles
     * @return array<int, array<string, string>>
     */
    private function roleOptions(array $roles): array
    {
        return array_map(
            fn (RoleName $role): array => ['value' => $role->value, 'label' => $role->label()],
            $roles,
        );
    }
}
