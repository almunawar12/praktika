<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;

/**
 * Failed attempts are the signal that matters for detecting credential stuffing,
 * so they are logged even though no user is authenticated.
 */
class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        activity('auth')
            ->causedBy($user)
            ->event('login_failed')
            ->withProperties([
                // Only the email is recorded. The submitted password must never
                // reach the log, so `$event->credentials` is not stored wholesale.
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Percobaan masuk gagal');
    }
}
