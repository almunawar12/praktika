<?php

declare(strict_types=1);

use App\Enums\AssessmentType;
use App\Enums\RoleName;
use App\Models\Assessment;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\FinalScore;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->company = Company::factory()->create(['quota' => 10]);
    $this->supervisor = CompanySupervisor::factory()->create(['company_id' => $this->company->id]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create();

    $this->placement = Placement::factory()->active()->create([
        'student_id' => $this->student->id,
        'company_id' => $this->company->id,
        'teacher_id' => $this->teacher->id,
    ]);
});

function scores(AssessmentType $type, float $value = 85.0): array
{
    $scores = [];

    foreach ($type->criteria() as $criteria) {
        $scores[$criteria->value] = $value;
    }

    return ['scores' => $scores];
}

test('industri submits its own side and guru submits theirs', function (): void {
    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$this->placement->id}/industry", [
            ...scores(AssessmentType::Industry, 90),
            'submit' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->teacher->user)
        ->put("/assessments/{$this->placement->id}/teacher", [
            ...scores(AssessmentType::Teacher, 80),
            'submit' => true,
        ])
        ->assertSessionHasNoErrors();

    expect(Assessment::count())->toBe(2);
});

test('the final score appears on its own once both sides submit', function (): void {
    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$this->placement->id}/industry", [
            ...scores(AssessmentType::Industry, 90),
            'submit' => true,
        ]);

    // Still nothing: one side is not a result.
    expect(FinalScore::count())->toBe(0);

    $this->actingAs($this->teacher->user)
        ->put("/assessments/{$this->placement->id}/teacher", [
            ...scores(AssessmentType::Teacher, 80),
            'submit' => true,
        ]);

    // AssessmentObserver: no admin has to remember to press Calculate.
    $score = FinalScore::firstOrFail();

    expect((float) $score->final_score)->toBe(86.0)
        ->and($score->isPublished())->toBeFalse();
});

test('a draft does not trigger the calculation', function (): void {
    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$this->placement->id}/industry", scores(AssessmentType::Industry, 90));
    $this->actingAs($this->teacher->user)
        ->put("/assessments/{$this->placement->id}/teacher", scores(AssessmentType::Teacher, 80));

    expect(Assessment::whereNotNull('submitted_at')->count())->toBe(0)
        ->and(FinalScore::count())->toBe(0);
});

test('a draft can be revised, a submitted assessment can not', function (): void {
    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$this->placement->id}/industry", scores(AssessmentType::Industry, 70));

    // Still a draft: come back tomorrow and change your mind.
    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$this->placement->id}/industry", [
            ...scores(AssessmentType::Industry, 90),
            'submit' => true,
        ])
        ->assertSessionHasNoErrors();

    expect((float) Assessment::firstOrFail()->average_score)->toBe(90.0);

    // Now it is evidence the final score rests on.
    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$this->placement->id}/industry", scores(AssessmentType::Industry, 100))
        ->assertSessionHas('error');

    expect((float) Assessment::firstOrFail()->average_score)->toBe(90.0);
});

test('only an admin may reopen a submitted assessment', function (): void {
    $assessment = Assessment::factory()->industry()->submitted()->create([
        'placement_id' => $this->placement->id,
    ]);

    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$assessment->id}/unlock")
        ->assertForbidden();

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->put("/assessments/{$assessment->id}/unlock")
        ->assertSessionHasNoErrors();

    expect($assessment->refresh()->isDraft())->toBeTrue();
});

test('neither side may submit the other side', function (): void {
    // The company judges disiplin; the school judges kehadiran. Swapping them would
    // let each grade what they never saw.
    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$this->placement->id}/teacher", [
            ...scores(AssessmentType::Teacher, 90),
            'submit' => true,
        ])
        ->assertForbidden();

    $this->actingAs($this->teacher->user)
        ->put("/assessments/{$this->placement->id}/industry", [
            ...scores(AssessmentType::Industry, 90),
            'submit' => true,
        ])
        ->assertForbidden();
});

test('criteria from the other side are rejected', function (): void {
    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$this->placement->id}/industry", [
            'scores' => [
                'disiplin' => 90,
                'tanggung_jawab' => 90,
                'kerjasama' => 90,
                'komunikasi' => 90,
                'kompetensi_teknis' => 90,
                // The school's to judge, not the company's.
                'kehadiran' => 100,
            ],
        ])
        ->assertSessionHasErrors('scores.kehadiran');
});

test('every criterion for this side is required', function (): void {
    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$this->placement->id}/industry", [
            'scores' => ['disiplin' => 90],
        ])
        ->assertSessionHasErrors(['scores.tanggung_jawab', 'scores.kompetensi_teknis']);
});

test('scores outside 0 to 100 are refused', function (float $value): void {
    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$this->placement->id}/industry", scores(AssessmentType::Industry, $value))
        ->assertSessionHasErrors('scores.disiplin');
})->with([-1.0, 101.0]);

test('the database refuses a score outside 0 to 100', function (): void {
    $assessment = Assessment::factory()->industry()->create(['placement_id' => $this->placement->id]);

    expect(fn () => $assessment->scores()->create([
        'criteria_code' => 'disiplin',
        'score' => 150,
    ]))->toThrow(QueryException::class);
});

test('a supervisor can not assess a placement at another company', function (): void {
    $other = Placement::factory()->active()->create();

    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$other->id}/industry", [
            ...scores(AssessmentType::Industry),
            'submit' => true,
        ])
        ->assertForbidden();
});

test('a draft placement can not be assessed', function (): void {
    $draft = Placement::factory()->create([
        'company_id' => $this->company->id,
        'teacher_id' => $this->teacher->id,
    ]);

    $this->actingAs($this->supervisor->user)
        ->put("/assessments/{$draft->id}/industry", [
            ...scores(AssessmentType::Industry),
            'submit' => true,
        ])
        ->assertForbidden();
});

test('a student never sees raw assessments', function (): void {
    Assessment::factory()->industry()->submitted()->create(['placement_id' => $this->placement->id]);

    // Siswa holds no assessment permission at all — only the published final score.
    $this->actingAs($this->student->user)->get('/assessments')->assertForbidden();
});

test('one assessment per side per placement', function (): void {
    Assessment::factory()->industry()->create(['placement_id' => $this->placement->id]);

    expect(fn () => Assessment::factory()->industry()->create([
        'placement_id' => $this->placement->id,
    ]))->toThrow(QueryException::class);
});
