<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Str;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember,
        public ?string $ip,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            email: (string) $request->string('email'),
            password: (string) $request->string('password'),
            remember: $request->boolean('remember'),
            ip: $request->ip(),
        );
    }

    /**
     * Rate limiter key: per email *and* per IP, so one attacker cannot lock out
     * a victim's account from an unrelated address.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.$this->ip);
    }

    /**
     * @return array{email: string, password: string}
     */
    public function credentials(): array
    {
        return ['email' => $this->email, 'password' => $this->password];
    }
}
