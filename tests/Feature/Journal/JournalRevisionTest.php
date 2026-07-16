<?php

declare(strict_types=1);

use App\Enums\ReviewStatus;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->company = Company::factory()->create(['quota' => 10]);
    $this->supervisor = CompanySupervisor::factory()->create(['company_id' => $this->company->id]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create();

    $this->placement = Placement::factory()->active()->create([
        'student_id' => $this->student->id,
        'company_id' => $this->company->id,
        'teacher_id' => $this->teacher->id,
        'start_date' => CarbonImmutable::now()->subMonth()->toDateString(),
        'end_date' => CarbonImmutable::now()->addMonth()->toDateString(),
    ]);
});

function content(array $overrides = []): array
{
    return array_merge([
        'activity' => 'Membantu tim QA melakukan regression testing modul login.',
        'work_result' => 'Menemukan 3 bug dan mendokumentasikannya di issue tracker.',
        'obstacle' => null,
    ], $overrides);
}

/*
| A rejected journal must be fixable. UNIQUE(placement_id, date) stops the student
| writing that day again, so without a revision path the day would be lost forever.
*/

test('a rejected journal is reopened for revision', function (): void {
    $journal = Journal::factory()->industryRejected()->create([
        'placement_id' => $this->placement->id,
    ]);

    expect($journal->isEditable())->toBeTrue();

    $this->actingAs($this->student->user)
        ->put("/journals/{$journal->id}", content(['activity' => 'Uraian yang jauh lebih lengkap dan jelas.']))
        ->assertSessionHasNoErrors();

    $journal->refresh();

    // Saving a revision sends it back to industry.
    expect($journal->industry_status)->toBe(ReviewStatus::Pending)
        ->and($journal->status)->toBe(ReviewStatus::Pending)
        ->and($journal->activity)->toBe('Uraian yang jauh lebih lengkap dan jelas.');
});

test('the rejection note survives the revision as the feedback being acted on', function (): void {
    $journal = Journal::factory()->industryRejected()->create([
        'placement_id' => $this->placement->id,
    ]);

    $this->actingAs($this->student->user)->put("/journals/{$journal->id}", content());

    expect($journal->refresh()->industry_note)->toBe('Uraian kegiatan terlalu singkat.');
});

test('a teacher rejection resets both stages, not just the teacher', function (): void {
    $journal = Journal::factory()->teacherRejected()->create([
        'placement_id' => $this->placement->id,
    ]);

    expect($journal->isEditable())->toBeTrue();

    $this->actingAs($this->student->user)
        ->put("/journals/{$journal->id}", content(['work_result' => 'Hasil pekerjaan yang sudah diperbaiki sesuai arahan.']));

    $journal->refresh();

    // The industry approved text the student has now rewritten; that approval can
    // not be carried over to content they never saw.
    expect($journal->industry_status)->toBe(ReviewStatus::Pending)
        ->and($journal->teacher_status)->toBe(ReviewStatus::Pending)
        ->and($journal->status)->toBe(ReviewStatus::Pending);
});

test('a journal industry approved is locked while the teacher reviews', function (): void {
    $journal = Journal::factory()->industryApproved()->create([
        'placement_id' => $this->placement->id,
    ]);

    expect($journal->isEditable())->toBeFalse();

    $this->actingAs($this->student->user)
        ->put("/journals/{$journal->id}", content())
        ->assertForbidden();
});

test('a fully approved journal is locked for good', function (): void {
    $journal = Journal::factory()->approved()->create(['placement_id' => $this->placement->id]);

    expect($journal->isEditable())->toBeFalse();

    $this->actingAs($this->student->user)
        ->put("/journals/{$journal->id}", content())
        ->assertForbidden();

    $this->actingAs($this->student->user)
        ->delete("/journals/{$journal->id}")
        ->assertForbidden();
});

test('a pending journal is editable — nobody has looked at it yet', function (): void {
    $journal = Journal::factory()->create(['placement_id' => $this->placement->id]);

    expect($journal->isEditable())->toBeTrue();

    $this->actingAs($this->student->user)
        ->put("/journals/{$journal->id}", content())
        ->assertSessionHasNoErrors();
});

test('a student can not edit another students journal', function (): void {
    $other = Journal::factory()->create();

    $this->actingAs($this->student->user)
        ->put("/journals/{$other->id}", content())
        ->assertForbidden();
});

test('the journal date can not be moved to another day', function (): void {
    $journal = Journal::factory()->create(['placement_id' => $this->placement->id]);

    // Moving it would collide with that day's own entry and rewrite history.
    $this->actingAs($this->student->user)
        ->put("/journals/{$journal->id}", content(['date' => '2026-01-01']))
        ->assertSessionHasErrors('date');
});

test('a full round trip: rejected, revised, approved by both', function (): void {
    $journal = Journal::factory()->create(['placement_id' => $this->placement->id]);

    $this->actingAs($this->supervisor->user)->put("/journals/{$journal->id}/review/industry", [
        'decision' => 'rejected',
        'note' => 'Kurang detail.',
    ]);
    expect($journal->refresh()->status)->toBe(ReviewStatus::Rejected);

    $this->actingAs($this->student->user)->put("/journals/{$journal->id}", content());
    expect($journal->refresh()->status)->toBe(ReviewStatus::Pending);

    $this->actingAs($this->supervisor->user)
        ->put("/journals/{$journal->id}/review/industry", ['decision' => 'approved']);
    $this->actingAs($this->teacher->user)
        ->put("/journals/{$journal->id}/review/teacher", ['decision' => 'approved']);

    expect($journal->refresh()->status)->toBe(ReviewStatus::Approved)
        ->and($journal->isEditable())->toBeFalse();
});
