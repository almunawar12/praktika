<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Session-based impersonation: an admin can act as another user to reproduce what
 * they see, then step back out.
 *
 * The original admin's id is parked in the session; `stop` restores it. Starting
 * is gated by UserPolicy::impersonate (Super Admin, never yourself); stopping is
 * open to whoever is currently impersonated, since that is the only account they
 * are holding.
 */
class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    public function store(Request $request, User $user): RedirectResponse
    {
        $this->authorize('impersonate', $user);

        // No nesting: stepping from one impersonation straight into another would
        // lose the original admin's id.
        if ($request->session()->has(self::SESSION_KEY)) {
            return back()->with('error', 'Hentikan impersonasi yang sedang berjalan terlebih dahulu.');
        }

        // A suspended account is unreachable by design; do not let impersonation be a
        // side door into one.
        if (! $user->is_active) {
            return back()->with('error', 'Tidak dapat menyamar sebagai akun yang nonaktif.');
        }

        activity('impersonation')
            ->performedOn($user)
            ->causedBy($request->user())
            ->log('impersonation.start');

        $request->session()->put(self::SESSION_KEY, $request->user()->id);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', "Anda kini menyamar sebagai {$user->name}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $originalId = $request->session()->pull(self::SESSION_KEY);

        if (! is_string($originalId)) {
            return redirect()->route('dashboard');
        }

        $original = User::find($originalId);

        if ($original === null) {
            // The admin account vanished mid-impersonation; drop the session rather
            // than leave someone logged in as a user they never were.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Auth::login($original);
        $request->session()->regenerate();

        return redirect()->route('system.users.index')->with('success', 'Impersonasi dihentikan.');
    }
}
