<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\SidebarBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly SidebarBuilder $sidebar) {}

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : $this->userPayload($user),
                'permissions' => $user === null
                    ? []
                    : $user->getAllPermissions()->pluck('name')->all(),
            ],
            'sidebar' => $user === null ? [] : $this->sidebar->build($user),
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Only the fields the frontend renders.
     *
     * Sharing the model wholesale would leak every column later added to `users`
     * into the page payload, where it is readable by anyone viewing source.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $role = $user->roleName();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar_path,
            'role' => $role?->value,
            'roleLabel' => $role?->label(),
        ];
    }
}
