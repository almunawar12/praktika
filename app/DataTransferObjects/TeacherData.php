<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\Master\StoreTeacherRequest;
use App\Http\Requests\Master\UpdateTeacherRequest;

final readonly class TeacherData
{
    public function __construct(
        public string $nip,
        public string $name,
        public string $email,
        public ?string $phone,
    ) {}

    public static function fromRequest(StoreTeacherRequest|UpdateTeacherRequest $request): self
    {
        return new self(
            nip: (string) $request->string('nip'),
            name: (string) $request->string('name'),
            email: (string) $request->string('email'),
            phone: $request->filled('phone') ? (string) $request->string('phone') : null,
        );
    }
}
