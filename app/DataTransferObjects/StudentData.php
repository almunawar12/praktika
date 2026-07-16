<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\Master\StoreStudentRequest;
use App\Http\Requests\Master\UpdateStudentRequest;

final readonly class StudentData
{
    public function __construct(
        public string $nis,
        public string $nisn,
        public string $name,
        public string $email,
        public ?string $phone,
        public string $classId,
    ) {}

    public static function fromRequest(StoreStudentRequest|UpdateStudentRequest $request): self
    {
        return new self(
            nis: (string) $request->string('nis'),
            nisn: (string) $request->string('nisn'),
            name: (string) $request->string('name'),
            email: (string) $request->string('email'),
            phone: $request->filled('phone') ? (string) $request->string('phone') : null,
            classId: (string) $request->string('class_id'),
        );
    }
}
