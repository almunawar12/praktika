<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\Pkl\StorePlacementRequest;
use App\Http\Requests\Pkl\UpdatePlacementRequest;

final readonly class PlacementData
{
    public function __construct(
        public string $studentId,
        public string $companyId,
        public string $teacherId,
        public ?string $academicYearId,
        public string $startDate,
        public string $endDate,
    ) {}

    public static function fromRequest(StorePlacementRequest|UpdatePlacementRequest $request): self
    {
        return new self(
            studentId: (string) $request->string('student_id'),
            companyId: (string) $request->string('company_id'),
            teacherId: (string) $request->string('teacher_id'),
            academicYearId: $request->filled('academic_year_id') ? (string) $request->string('academic_year_id') : null,
            startDate: (string) $request->string('start_date'),
            endDate: (string) $request->string('end_date'),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toAttributes(): array
    {
        return [
            'student_id' => $this->studentId,
            'company_id' => $this->companyId,
            'teacher_id' => $this->teacherId,
            'academic_year_id' => $this->academicYearId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ];
    }
}
