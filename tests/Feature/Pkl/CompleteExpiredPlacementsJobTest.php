<?php

declare(strict_types=1);

use App\Enums\PlacementStatus;
use App\Jobs\CompleteExpiredPlacementsJob;
use App\Models\Company;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->company = Company::factory()->create(['quota' => 10]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create();
});

test('the nightly job completes active placements past their end date', function (): void {
    $today = CarbonImmutable::parse('2026-07-20');

    $placement = Placement::factory()->active()->create([
        'student_id' => $this->student->id,
        'company_id' => $this->company->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => $today->subMonth()->toDateString(),
        'end_date' => $today->subDay()->toDateString(),
    ]);

    CompleteExpiredPlacementsJob::dispatchSync($today->toDateString());

    expect($placement->fresh()->status)->toBe(PlacementStatus::Completed);
});

test('the nightly job leaves active placements still within their term alone', function (): void {
    $today = CarbonImmutable::parse('2026-07-20');

    $placement = Placement::factory()->active()->create([
        'student_id' => $this->student->id,
        'company_id' => $this->company->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => $today->subMonth()->toDateString(),
        'end_date' => $today->addMonth()->toDateString(),
    ]);

    CompleteExpiredPlacementsJob::dispatchSync($today->toDateString());

    expect($placement->fresh()->status)->toBe(PlacementStatus::Active);
});

test('the nightly job does not touch draft or completed placements', function (): void {
    $today = CarbonImmutable::parse('2026-07-20');

    $draft = Placement::factory()->create([
        'student_id' => $this->student->id,
        'company_id' => $this->company->id,
        'teacher_id' => $this->teacher->id,
        'status' => PlacementStatus::Draft,
        'start_date' => $today->subMonth()->toDateString(),
        'end_date' => $today->subDay()->toDateString(),
    ]);

    CompleteExpiredPlacementsJob::dispatchSync($today->toDateString());

    expect($draft->fresh()->status)->toBe(PlacementStatus::Draft);
});
