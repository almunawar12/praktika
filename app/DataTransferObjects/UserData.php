<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\RoleName;

final readonly class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public bool $isActive,
        public ?RoleName $role,
        public ?string $password,
    ) {}
}
