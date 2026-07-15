<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Lockout;

class LogLockout
{
    public function handle(Lockout $event): void
    {
        activity('auth')
            ->event('lockout')
            ->withProperties([
                'email' => $event->request->input('email'),
                'ip' => $event->request->ip(),
            ])
            ->log('Terlalu banyak percobaan masuk, akun dikunci sementara');
    }
}
