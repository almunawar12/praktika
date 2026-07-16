<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\FinalScore;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->company = Company::factory()->create(['quota' => 10]);
    $this->supervisor = CompanySupervisor::factory()->create(['company_id' => $this->company->id]);
    $this->teacher = Teacher::factory()->create();
    $this->student = Student::factory()->create();
    $this->admin = userWithRole(RoleName::AdminPkl);

    $this->placement = Placement::factory()->completed()->create([
        'student_id' => $this->student->id,
        'company_id' => $this->company->id,
        'teacher_id' => $this->teacher->id,
    ]);

    $this->score = FinalScore::factory()->create(['placement_id' => $this->placement->id]);
});

/*
| A result exists for the school long before it exists for the student. These tests
| pin the line between the two.
*/

test('a student can not see an unpublished score', function (): void {
    $this->actingAs($this->student->user)
        ->get('/final-scores')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('scores.data', 0));

    $this->actingAs($this->student->user)
        ->get("/final-scores/{$this->score->id}")
        ->assertForbidden();
});

test('everyone else can watch the score take shape', function (): void {
    foreach ([$this->admin, $this->teacher->user, $this->supervisor->user] as $viewer) {
        $this->actingAs($viewer)
            ->get('/final-scores')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('scores.data', 1));
    }
});

test('publishing makes it visible to the student', function (): void {
    $this->actingAs($this->admin)
        ->post("/final-scores/{$this->score->id}/publish")
        ->assertSessionHasNoErrors();

    $this->score->refresh();

    expect($this->score->isPublished())->toBeTrue()
        ->and($this->score->published_by)->toBe($this->admin->id);

    $this->actingAs($this->student->user)
        ->get("/final-scores/{$this->score->id}")
        ->assertOk();
});

test('unpublishing hides it again', function (): void {
    $this->actingAs($this->admin)->post("/final-scores/{$this->score->id}/publish");
    $this->actingAs($this->admin)->delete("/final-scores/{$this->score->id}/publish");

    expect($this->score->refresh()->isPublished())->toBeFalse();

    $this->actingAs($this->student->user)
        ->get("/final-scores/{$this->score->id}")
        ->assertForbidden();
});

test('publishing twice is refused', function (): void {
    $this->actingAs($this->admin)->post("/final-scores/{$this->score->id}/publish");

    $this->actingAs($this->admin)
        ->post("/final-scores/{$this->score->id}/publish")
        ->assertForbidden();
});

test('only an admin may publish', function (): void {
    foreach ([$this->teacher->user, $this->supervisor->user, $this->student->user] as $user) {
        $this->actingAs($user)
            ->post("/final-scores/{$this->score->id}/publish")
            ->assertForbidden();
    }

    expect($this->score->refresh()->isPublished())->toBeFalse();
});

test('a guru sees only their own supervision', function (): void {
    FinalScore::factory()->count(3)->create();

    $this->actingAs($this->teacher->user)
        ->get('/final-scores')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('scores.data', 1));
});

test('a student sees only their own published score', function (): void {
    $this->score->update(['published_at' => now(), 'published_by' => $this->admin->id]);
    FinalScore::factory()->published()->count(3)->create();

    $this->actingAs($this->student->user)
        ->get('/final-scores')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('scores.data', 1));
});

test('the database refuses a publication with no publisher named', function (): void {
    expect(fn () => DB::table('final_scores')->insert([
        'id' => Str::uuid7()->toString(),
        'placement_id' => Placement::factory()->completed()->create()->id,
        'industry_score' => 90,
        'teacher_score' => 80,
        'industry_weight' => 0.6,
        'teacher_weight' => 0.4,
        'final_score' => 86,
        'grade' => 'B',
        'published_at' => now(),
        'published_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('one final score per placement', function (): void {
    expect(fn () => FinalScore::factory()->create([
        'placement_id' => $this->placement->id,
    ]))->toThrow(QueryException::class);
});
