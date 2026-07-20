<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\TableRequest;
use App\DataTransferObjects\UserData;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Direct management of `users` rows from the System area.
 *
 * This is the home for staff accounts — Super Admin and Admin PKL — which have no
 * master-data profile behind them. Guru, Siswa and Pembimbing Industri accounts
 * are born from their own modules through UserAccountService; this screen still
 * lists and can suspend them, but does not hand out their roles.
 */
class UserService
{
    public const SORTABLE = ['name', 'email', 'created_at', 'last_login_at'];

    /**
     * Roles this screen may grant. The profile-backed roles are intentionally
     * excluded: creating one here would make a user with no Student/Teacher row.
     *
     * @var array<int, RoleName>
     */
    public const ASSIGNABLE = [RoleName::SuperAdmin, RoleName::AdminPkl];

    /**
     * Totals for the overview cards.
     *
     * @return array{total: int, active: int, inactive: int, loggedIn: int}
     */
    public function stats(): array
    {
        $counts = User::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where is_active) as active')
            ->selectRaw('count(*) filter (where last_login_at is not null) as logged_in')
            ->first();

        $total = (int) ($counts?->getAttribute('total') ?? 0);
        $active = (int) ($counts?->getAttribute('active') ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'loggedIn' => (int) ($counts?->getAttribute('logged_in') ?? 0),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(TableRequest $table): LengthAwarePaginator
    {
        return User::query()
            ->with('roles:id,name')
            ->when($table->search, fn (Builder $query, string $search) => $query
                ->where(fn (Builder $q) => $q
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")))
            ->when($table->filter('role'), fn (Builder $query, string $role) => $query
                ->whereHas('roles', fn (Builder $q) => $q->where('name', $role)))
            ->when($table->filter('status'), fn (Builder $query, string $status) => $query
                ->where('is_active', $status === 'active'))
            ->orderBy($table->sortColumn(self::SORTABLE, 'name'), $table->sortDirection())
            ->paginate($table->perPage)
            ->withQueryString();
    }

    public function create(UserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'is_active' => $data->isActive,
                'password' => Hash::make($data->password ?? Str::password(32)),
            ]);

            $user->forceFill(['email_verified_at' => now()])->saveQuietly();

            if ($data->role !== null) {
                $user->syncRoles([$data->role->value]);
            }

            return $user;
        });
    }

    public function update(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->update([
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'is_active' => $data->isActive,
            ]);

            // Role is only ever changed for staff accounts. A profile-backed user
            // keeps the role its module gave it — see ASSIGNABLE.
            if ($data->role !== null && ! $this->hasProfile($user)) {
                $user->syncRoles([$data->role->value]);
            }

            return $user;
        });
    }

    public function delete(User $user): void
    {
        // Soft delete: `users` is the causer on audit-log entries that must stay
        // attributable after the account is gone.
        $user->delete();
    }

    public function toggleStatus(User $user): bool
    {
        $user->update(['is_active' => ! $user->is_active]);

        return $user->is_active;
    }

    /**
     * Issue a fresh password and return it in the clear exactly once, for the admin
     * to hand over. It is never stored anywhere readable.
     */
    public function resetPassword(User $user): string
    {
        $password = Str::password(12);

        $user->update(['password' => Hash::make($password)]);
        $user->setRememberToken(Str::random(60));
        $user->save();

        return $password;
    }

    public function hasProfile(User $user): bool
    {
        return $user->student()->exists()
            || $user->teacher()->exists()
            || $user->companySupervisor()->exists();
    }
}
