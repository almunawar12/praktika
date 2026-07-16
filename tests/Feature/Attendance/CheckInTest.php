<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Placement;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');

    // Company sits at a known point so geofence distances are exact, not fuzzy.
    $this->company = Company::factory()->create([
        'quota' => 10,
        'latitude' => -6.9147440,
        'longitude' => 107.6098100,
        'geofence_radius_m' => 200,
    ]);

    $this->student = Student::factory()->create();

    $this->placement = Placement::factory()->active()->create([
        'student_id' => $this->student->id,
        'company_id' => $this->company->id,
        'start_date' => CarbonImmutable::now()->subMonth()->toDateString(),
        'end_date' => CarbonImmutable::now()->addMonth()->toDateString(),
    ]);
});

function atCompany(array $overrides = []): array
{
    return array_merge([
        'latitude' => -6.9147440,
        'longitude' => 107.6098100,
        'photo' => UploadedFile::fake()->image('selfie.jpg'),
    ], $overrides);
}

test('a student can check in from the company location', function (): void {
    $this->actingAs($this->student->user)
        ->post('/attendances/check-in', atCompany())
        ->assertSessionHasNoErrors();

    $attendance = Attendance::firstOrFail();

    expect($attendance->status)->toBe(AttendanceStatus::Present)
        ->and($attendance->placement_id)->toBe($this->placement->id)
        ->and($attendance->check_in_at)->not->toBeNull()
        ->and($attendance->check_in_photo_path)->not->toBeNull()
        // Verification is the supervisor's call, never automatic.
        ->and($attendance->verification_status)->toBe(VerificationStatus::Pending);

    Storage::disk('local')->assertExists($attendance->check_in_photo_path);
});

test('the recorded distance is stored', function (): void {
    $this->actingAs($this->student->user)->post('/attendances/check-in', atCompany());

    // Same coordinates as the company: distance must be ~0.
    expect(Attendance::firstOrFail()->check_in_distance_m)->toBeLessThan(5);
});

test('a check in from outside the geofence is refused', function (): void {
    // ~1.5 km north of the company, well beyond the 200 m radius.
    $this->actingAs($this->student->user)
        ->post('/attendances/check-in', atCompany(['latitude' => -6.9012000]))
        ->assertSessionHas('error');

    expect(Attendance::count())->toBe(0);
});

test('a refused check in leaves no orphan photo behind', function (): void {
    $this->actingAs($this->student->user)
        ->post('/attendances/check-in', atCompany(['latitude' => -6.9012000]));

    // The photo is only written once every rule has passed.
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('a company without coordinates has no geofence to enforce', function (): void {
    $company = Company::factory()->withoutGeofence()->create(['quota' => 5]);
    $student = Student::factory()->create();
    Placement::factory()->active()->create([
        'student_id' => $student->id,
        'company_id' => $company->id,
        'start_date' => CarbonImmutable::now()->subDay()->toDateString(),
        'end_date' => CarbonImmutable::now()->addMonth()->toDateString(),
    ]);

    // An unfilled admin field must not stop a student from working.
    $this->actingAs($student->user)
        ->post('/attendances/check-in', atCompany(['latitude' => 0, 'longitude' => 0]))
        ->assertSessionHasNoErrors();

    expect(Attendance::firstOrFail()->check_in_distance_m)->toBeNull();
});

test('a student can not check in twice on the same day', function (): void {
    $this->actingAs($this->student->user)->post('/attendances/check-in', atCompany());

    $this->actingAs($this->student->user)
        ->post('/attendances/check-in', atCompany())
        ->assertSessionHas('error');

    expect(Attendance::count())->toBe(1);
});

test('the database refuses a second record for the same day even if the service check is bypassed', function (): void {
    Attendance::factory()->create([
        'placement_id' => $this->placement->id,
        'date' => CarbonImmutable::now()->toDateString(),
    ]);

    // Two taps on a flaky connection both pass the service check.
    expect(fn () => Attendance::factory()->create([
        'placement_id' => $this->placement->id,
        'date' => CarbonImmutable::now()->toDateString(),
    ]))->toThrow(QueryException::class);
});

test('check in requires both a photo and a location', function (): void {
    $this->actingAs($this->student->user)
        ->post('/attendances/check-in', ['latitude' => -6.9147440, 'longitude' => 107.6098100])
        ->assertSessionHasErrors('photo');

    $this->actingAs($this->student->user)
        ->post('/attendances/check-in', ['photo' => UploadedFile::fake()->image('a.jpg')])
        ->assertSessionHasErrors(['latitude', 'longitude']);

    expect(Attendance::count())->toBe(0);
});

test('a non-image upload is refused', function (): void {
    $this->actingAs($this->student->user)
        ->post('/attendances/check-in', atCompany([
            'photo' => UploadedFile::fake()->create('payload.php', 100, 'application/x-php'),
        ]))
        ->assertSessionHasErrors('photo');
});

test('the database refuses a present record with no evidence', function (): void {
    // The CHECK constraint is the last line: `present` is a claim about being
    // somewhere, so it must carry the proof.
    expect(fn () => DB::table('attendances')->insert([
        'id' => Str::uuid7()->toString(),
        'placement_id' => $this->placement->id,
        'date' => CarbonImmutable::now()->toDateString(),
        'status' => 'present',
        'verification_status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('a student without an active placement can not check in', function (): void {
    $orphan = Student::factory()->create();

    $this->actingAs($orphan->user)
        ->post('/attendances/check-in', atCompany())
        ->assertSessionHas('error');

    expect(Attendance::count())->toBe(0);
});

test('check in outside the placement period is refused', function (): void {
    $student = Student::factory()->create();
    Placement::factory()->active()->create([
        'student_id' => $student->id,
        'company_id' => $this->company->id,
        'start_date' => CarbonImmutable::now()->addWeek()->toDateString(),
        'end_date' => CarbonImmutable::now()->addMonths(3)->toDateString(),
    ]);

    $this->actingAs($student->user)
        ->post('/attendances/check-in', atCompany())
        ->assertSessionHas('error');

    expect(Attendance::count())->toBe(0);
});
