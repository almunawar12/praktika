<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;

/**
 * The account actions that are not plain field edits: suspending an account and
 * issuing a new password. Both are gated by UserPolicy, which — with no
 * Gate::before bypass — binds Super Admins too (no deactivating the last one).
 */
class UserModerationController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function toggleStatus(User $user): RedirectResponse
    {
        $this->authorize('toggleStatus', $user);

        $active = $this->users->toggleStatus($user);

        return back()->with(
            'success',
            $active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan. Sesi aktifnya akan berakhir.',
        );
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $password = $this->users->resetPassword($user);

        // Shown once, for the admin to hand over. It is never stored in the clear.
        return back()->with('success', "Kata sandi baru untuk {$user->name}: {$password}");
    }
}
