<?php

declare(strict_types=1);

use App\Enums\ReviewStatus;
use App\Enums\RoleName;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

function journalFor(Placement $placement, array $overrides = []): Journal
{
    return Journal::factory()->create(array_merge(['placement_id' => $placement->id], $overrides));
}

test('the happy path is siswa to industri to guru', function (): void {
    $journal = journalFor($this->placement);

    $this->actingAs($this->supervisor->user)
        ->put("/journals/{$journal->id}/review/industry", ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    $journal->refresh();
    expect($journal->industry_status)->toBe(ReviewStatus::Approved)
        // Not approved yet — the teacher has not spoken.
        ->and($journal->status)->toBe(ReviewStatus::Pending);

    $this->actingAs($this->teacher->user)
        ->put("/journals/{$journal->id}/review/teacher", ['decision' => 'approved'])
        ->assertSessionHasNoErrors();

    expect($journal->refresh()->status)->toBe(ReviewStatus::Approved);
});

test('a teacher can not review before industry has approved', function (): void {
    $journal = journalFor($this->placement);

    // PRD § 5 order: the company signs off first.
    $this->actingAs($this->teacher->user)
        ->put("/journals/{$journal->id}/review/teacher", ['decision' => 'approved'])
        ->assertForbidden();

    expect($journal->refresh()->teacher_status)->toBe(ReviewStatus::Pending);
});

test('a teacher can not review a journal industry rejected', function (): void {
    $journal = journalFor($this->placement, [
        'status' => ReviewStatus::Rejected,
        'industry_status' => ReviewStatus::Rejected,
        'industry_reviewed_by' => $this->supervisor->user_id,
        'industry_reviewed_at' => now(),
    ]);

    $this->actingAs($this->teacher->user)
        ->put("/journals/{$journal->id}/review/teacher", ['decision' => 'approved'])
        ->assertForbidden();
});

test('the database refuses a teacher decision without industry approval', function (): void {
    // The last line of defence behind the PRD order.
    expect(fn () => DB::table('journals')->insert([
        'id' => Str::uuid7()->toString(),
        'placement_id' => $this->placement->id,
        'date' => '2026-07-01',
        'activity' => 'x',
        'work_result' => 'y',
        'status' => 'approved',
        'industry_status' => 'pending',
        'teacher_status' => 'approved',
        'teacher_reviewed_by' => $this->teacher->user_id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('the database refuses a decision with no reviewer named', function (): void {
    expect(fn () => DB::table('journals')->insert([
        'id' => Str::uuid7()->toString(),
        'placement_id' => $this->placement->id,
        'date' => '2026-07-02',
        'activity' => 'x',
        'work_result' => 'y',
        'status' => 'pending',
        'industry_status' => 'approved',
        'industry_reviewed_by' => null,
        'teacher_status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('rejecting requires a reason the student can act on', function (): void {
    $journal = journalFor($this->placement);

    $this->actingAs($this->supervisor->user)
        ->put("/journals/{$journal->id}/review/industry", ['decision' => 'rejected'])
        ->assertSessionHasErrors('note');

    $this->actingAs($this->supervisor->user)
        ->put("/journals/{$journal->id}/review/industry", [
            'decision' => 'rejected',
            'note' => 'Uraian terlalu singkat.',
        ])
        ->assertSessionHasNoErrors();

    $journal->refresh();

    expect($journal->industry_status)->toBe(ReviewStatus::Rejected)
        ->and($journal->status)->toBe(ReviewStatus::Rejected)
        ->and($journal->industry_note)->toBe('Uraian terlalu singkat.');
});

test('a reviewer can not put a journal back to pending', function (): void {
    $journal = journalFor($this->placement);

    // `pending` is a state, not a decision.
    $this->actingAs($this->supervisor->user)
        ->put("/journals/{$journal->id}/review/industry", ['decision' => 'pending'])
        ->assertSessionHasErrors('decision');
});

test('a teacher decision can never outlive the industry approval it depended on', function (): void {
    $journal = journalFor($this->placement, [
        'industry_status' => ReviewStatus::Approved,
        'industry_reviewed_by' => $this->supervisor->user_id,
        'industry_reviewed_at' => now(),
        'teacher_status' => ReviewStatus::Approved,
        'teacher_reviewed_by' => $this->teacher->user_id,
        'teacher_reviewed_at' => now(),
    ]);

    // Walking industry back to pending while a teacher verdict stands is exactly
    // the inconsistency journals_teacher_requires_industry_check exists to stop —
    // which is why the service never has to clear a stale teacher decision.
    expect(fn () => DB::table('journals')->where('id', $journal->id)->update([
        'industry_status' => 'pending',
    ]))->toThrow(QueryException::class);
});

test('a supervisor can not review a journal from another company', function (): void {
    $other = journalFor(Placement::factory()->active()->create());

    $this->actingAs($this->supervisor->user)
        ->put("/journals/{$other->id}/review/industry", ['decision' => 'approved'])
        ->assertForbidden();
});

test('a guru can not review a journal outside their supervision', function (): void {
    $other = journalFor(Placement::factory()->active()->create(), [
        'industry_status' => ReviewStatus::Approved,
        'industry_reviewed_by' => $this->supervisor->user_id,
        'industry_reviewed_at' => now(),
    ]);

    $this->actingAs($this->teacher->user)
        ->put("/journals/{$other->id}/review/teacher", ['decision' => 'approved'])
        ->assertForbidden();
});

test('a student can not review their own journal at either stage', function (): void {
    $journal = journalFor($this->placement);

    $this->actingAs($this->student->user)
        ->put("/journals/{$journal->id}/review/industry", ['decision' => 'approved'])
        ->assertForbidden();

    $this->actingAs($this->student->user)
        ->put("/journals/{$journal->id}/review/teacher", ['decision' => 'approved'])
        ->assertForbidden();
});

test('an admin can not review either — that is the supervisors job', function (): void {
    $journal = journalFor($this->placement);

    // Admin PKL holds journals.viewAny but neither review permission.
    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->put("/journals/{$journal->id}/review/industry", ['decision' => 'approved'])
        ->assertForbidden();
});

test('a journal already decided by industry can not be re-decided', function (): void {
    $journal = journalFor($this->placement, [
        'industry_status' => ReviewStatus::Approved,
        'industry_reviewed_by' => $this->supervisor->user_id,
        'industry_reviewed_at' => now(),
    ]);

    $this->actingAs($this->supervisor->user)
        ->put("/journals/{$journal->id}/review/industry", ['decision' => 'rejected', 'note' => 'berubah pikiran'])
        ->assertForbidden();
});
