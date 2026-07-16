<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\Master\StoreCompanySupervisorRequest;
use App\Http\Requests\Master\UpdateCompanySupervisorRequest;

final readonly class CompanySupervisorData
{
    public function __construct(
        public string $companyId,
        public string $name,
        public string $email,
        public ?string $position,
        public ?string $phone,
    ) {}

    public static function fromRequest(
        StoreCompanySupervisorRequest|UpdateCompanySupervisorRequest $request,
    ): self {
        return new self(
            companyId: (string) $request->string('company_id'),
            name: (string) $request->string('name'),
            email: (string) $request->string('email'),
            position: $request->filled('position') ? (string) $request->string('position') : null,
            phone: $request->filled('phone') ? (string) $request->string('phone') : null,
        );
    }
}
