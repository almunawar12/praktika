<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\LoginData;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Maximum failed attempts before the email+IP pair is locked out.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * @throws ValidationException
     */
    public function authenticate(LoginData $data): User
    {
        $this->ensureIsNotRateLimited($data);

        if (! Auth::attempt($data->credentials(), $data->remember)) {
            RateLimiter::hit($data->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Checked after the credentials pass so that a wrong password on a disabled
        // account still returns the generic failure message, revealing nothing.
        if (! $user->is_active) {
            Auth::guard('web')->logout();

            RateLimiter::hit($data->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('Akun Anda telah dinonaktifkan. Hubungi administrator.'),
            ]);
        }

        RateLimiter::clear($data->throttleKey());

        return $user;
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(LoginData $data): void
    {
        if (! RateLimiter::tooManyAttempts($data->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($data->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }
}
