<?php

declare(strict_types=1);

use App\Enums\PlacementStatus;
use App\Enums\RoleName;
use App\Models\Company;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->admin = userWithRole(RoleName::AdminPkl);
});

test('a student can not have two active placements', function (): void {
    $student = Student::factory()->create();
    Placement::factory()->active()->create(['student_id' => $student->id]);

    $second = Placement::factory()->create(['student_id' => $student->id]);

    $this->actingAs($this->admin)
        ->put("/pkl/placements/{$second->id}/activate")
        ->assertSessionHas('error');

    expect($second->refresh()->status)->toBe(PlacementStatus::Draft);
});

test('the database refuses a second active placement even if the service check is bypassed', function (): void {
    $student = Student::factory()->create();
    Placement::factory()->active()->create(['student_id' => $student->id]);

    // Two admins activating at the same moment both pass the service check; the
    // partial unique index is the only thing that actually stops the race.
    expect(fn () => DB::table('placements')->insert([
        'id' => Str::uuid7()->toString(),
        'student_id' => $student->id,
        'company_id' => Company::factory()->create()->id,
        'teacher_id' => Teacher::factory()->create()->id,
        'status' => 'active',
        'start_date' => '2026-01-01',
        'end_date' => '2026-04-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('a completed placement frees the student to start a new one', function (): void {
    $student = Student::factory()->create();
    Placement::factory()->completed()->create(['student_id' => $student->id]);

    $next = Placement::factory()->create(['student_id' => $student->id]);

    $this->actingAs($this->admin)
        ->put("/pkl/placements/{$next->id}/activate")
        ->assertSessionHasNoErrors();

    expect($next->refresh()->status)->toBe(PlacementStatus::Active);
});

test('a cancelled placement frees the student to start a new one', function (): void {
    $student = Student::factory()->create();
    Placement::factory()->cancelled()->create(['student_id' => $student->id]);

    $next = Placement::factory()->create(['student_id' => $student->id]);

    $this->actingAs($this->admin)->put("/pkl/placements/{$next->id}/activate");

    expect($next->refresh()->status)->toBe(PlacementStatus::Active);
});

test('a placement can not be activated once the company quota is full', function (): void {
    $company = Company::factory()->create(['quota' => 2]);
    Placement::factory()->active()->count(2)->create(['company_id' => $company->id]);

    $third = Placement::factory()->create(['company_id' => $company->id]);

    $this->actingAs($this->admin)
        ->put("/pkl/placements/{$third->id}/activate")
        ->assertSessionHas('error');

    expect($third->refresh()->status)->toBe(PlacementStatus::Draft);
});

test('quota counts only placements that hold a slot', function (): void {
    $company = Company::factory()->create(['quota' => 2]);

    // Draft, completed and cancelled placements must not consume the quota.
    Placement::factory()->count(3)->create(['company_id' => $company->id]);
    Placement::factory()->completed()->count(3)->create(['company_id' => $company->id]);
    Placement::factory()->cancelled()->count(3)->create(['company_id' => $company->id]);

    $placement = Placement::factory()->create(['company_id' => $company->id]);

    $this->actingAs($this->admin)
        ->put("/pkl/placements/{$placement->id}/activate")
        ->assertSessionHasNoErrors();

    expect($placement->refresh()->status)->toBe(PlacementStatus::Active);
});

test('a draft may be created even when the company is already full', function (): void {
    $company = Company::factory()->create(['quota' => 1]);
    Placement::factory()->active()->create(['company_id' => $company->id]);

    // Planning ahead is fine; only activation is gated.
    $this->actingAs($this->admin)
        ->post('/pkl/placements', [
            'student_id' => Student::factory()->create()->id,
            'company_id' => $company->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-11-01',
        ])
        ->assertSessionHasNoErrors();

    expect(Placement::where('company_id', $company->id)->count())->toBe(2);
});

test('an inactive company can not receive an active placement', function (): void {
    $company = Company::factory()->inactive()->create(['quota' => 10]);
    $placement = Placement::factory()->create(['company_id' => $company->id]);

    $this->actingAs($this->admin)
        ->put("/pkl/placements/{$placement->id}/activate")
        ->assertSessionHas('error');

    expect($placement->refresh()->status)->toBe(PlacementStatus::Draft);
});

test('end date must not precede start date', function (): void {
    $this->actingAs($this->admin)
        ->post('/pkl/placements', [
            'student_id' => Student::factory()->create()->id,
            'company_id' => Company::factory()->create()->id,
            'teacher_id' => Teacher::factory()->create()->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-08-01',
        ])
        ->assertSessionHasErrors('end_date');
});

test('the database refuses reversed dates even if validation is bypassed', function (): void {
    expect(fn () => DB::table('placements')->insert([
        'id' => Str::uuid7()->toString(),
        'student_id' => Student::factory()->create()->id,
        'company_id' => Company::factory()->create()->id,
        'teacher_id' => Teacher::factory()->create()->id,
        'status' => 'draft',
        'start_date' => '2026-11-01',
        'end_date' => '2026-08-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('an unknown status is refused by the database', function (): void {
    expect(fn () => DB::table('placements')->insert([
        'id' => Str::uuid7()->toString(),
        'student_id' => Student::factory()->create()->id,
        'company_id' => Company::factory()->create()->id,
        'teacher_id' => Teacher::factory()->create()->id,
        'status' => 'whatever',
        'start_date' => '2026-01-01',
        'end_date' => '2026-04-01',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
