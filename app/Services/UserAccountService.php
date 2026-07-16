<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The single write path for the `users` rows that back a Guru or Siswa profile.
 *
 * Praktika has no public registration, so every such account is born here. Profile
 * tables keep their own `name`/`phone` (ADD § 1.1) while `users` holds the
 * credentials; both are written together so the two can not drift apart.
 */
class UserAccountService
{
    public function createFor(RoleName $role, string $name, string $email, ?string $phone): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            // The admin never sees this value: the account is handed over through the
            // password-reset flow, which is why it must be unguessable rather than a
            // shared default like "password".
            'password' => Hash::make(Str::password(32)),
            'phone' => $phone,
            'is_active' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->saveQuietly();
        $user->syncRoles([$role->value]);

        return $user;
    }

    /**
     * Keep the account in step with an edited profile.
     */
    public function syncFromProfile(User $user, string $name, string $email, ?string $phone): void
    {
        $user->update([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);
    }

    /**
     * Deactivate rather than delete: `users` is referenced by activity_log and, later,
     * by placements — rows that must stay readable after someone leaves.
     */
    public function deactivate(User $user): void
    {
        $user->update(['is_active' => false]);
    }

    public function reactivate(User $user): void
    {
        $user->update(['is_active' => true]);
    }
}
