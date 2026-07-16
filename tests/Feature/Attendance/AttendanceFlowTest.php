<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Jobs\MarkAbsentStudentsJob;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');

    $this->company = Company::factory()->create([
        'quota' => 10,
        'latitude' => -6.9147440,
        'longitude' => 107.6098100,
        'geofence_radius_m' => 200,
    ]);

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

function payload(): array
{
    return [
        'latitude' => -6.9147440,
        'longitude' => 107.6098100,
        'photo' => UploadedFile::fake()->image('selfie.jpg'),
    ];
}

test('check out requires an existing check in', function (): void {
    $this->actingAs($this->student->user)
        ->post('/attendances/check-out', payload())
        ->assertSessionHas('error');
});

test('a student can check out after checking in', function (): void {
    $this->actingAs($this->student->user)->post('/attendances/check-in', payload());
    $this->actingAs($this->student->user)
        ->post('/attendances/check-out', payload())
        ->assertSessionHasNoErrors();

    $attendance = Attendance::firstOrFail();

    expect($attendance->check_out_at)->not->toBeNull()
        ->and($attendance->check_out_photo_path)->not->toBeNull();
});

test('a student can not check out twice', function (): void {
    $this->actingAs($this->student->user)->post('/attendances/check-in', payload());
    $this->actingAs($this->student->user)->post('/attendances/check-out', payload());

    $this->actingAs($this->student->user)
        ->post('/attendances/check-out', payload())
        ->assertSessionHas('error');
});

test('a student can request izin or sakit with a reason', function (): void {
    $this->actingAs($this->student->user)
        ->post('/attendances/absence', ['status' => 'sick', 'notes' => 'Demam, ada surat dokter.'])
        ->assertSessionHasNoErrors();

    $attendance = Attendance::firstOrFail();

    expect($attendance->status)->toBe(AttendanceStatus::Sick)
        // The claim is the student's; the decision is the supervisor's.
        ->and($attendance->verification_status)->toBe(VerificationStatus::Pending)
        ->and($attendance->check_in_at)->toBeNull();
});

test('an absence request needs a reason', function (): void {
    $this->actingAs($this->student->user)
        ->post('/attendances/absence', ['status' => 'permit', 'notes' => ''])
        ->assertSessionHasErrors('notes');
});

test('a student can not declare themselves present or absent', function (string $status): void {
    // `present` must come from a real check-in; `absent` is the nightly job's call.
    $this->actingAs($this->student->user)
        ->post('/attendances/absence', ['status' => $status, 'notes' => 'mencoba curang'])
        ->assertSessionHasErrors('status');

    expect(Attendance::count())->toBe(0);
})->with(['present', 'absent']);

test('a teacher verifies attendance for their own students', function (): void {
    $attendance = Attendance::factory()->create(['placement_id' => $this->placement->id]);

    $this->actingAs($this->teacher->user)
        ->put("/attendances/{$attendance->id}/verify", ['decision' => 'verified'])
        ->assertSessionHasNoErrors();

    $attendance->refresh();

    expect($attendance->verification_status)->toBe(VerificationStatus::Verified)
        ->and($attendance->verified_by)->toBe($this->teacher->user->id);
});

test('rejecting attendance requires a reason the student can read', function (): void {
    $attendance = Attendance::factory()->create(['placement_id' => $this->placement->id]);

    $this->actingAs($this->teacher->user)
        ->put("/attendances/{$attendance->id}/verify", ['decision' => 'rejected'])
        ->assertSessionHasErrors('note');

    $this->actingAs($this->teacher->user)
        ->put("/attendances/{$attendance->id}/verify", [
            'decision' => 'rejected',
            'note' => 'Foto bukan di lokasi PKL.',
        ])
        ->assertSessionHasNoErrors();

    expect($attendance->refresh()->verification_status)->toBe(VerificationStatus::Rejected);
});

test('a student can not verify their own attendance', function (): void {
    $attendance = Attendance::factory()->create(['placement_id' => $this->placement->id]);

    // Would make the whole check meaningless.
    $this->actingAs($this->student->user)
        ->put("/attendances/{$attendance->id}/verify", ['decision' => 'verified'])
        ->assertForbidden();

    expect($attendance->refresh()->verification_status)->toBe(VerificationStatus::Pending);
});

test('a teacher can not verify attendance outside their supervision', function (): void {
    $other = Attendance::factory()->create();

    $this->actingAs($this->teacher->user)
        ->put("/attendances/{$other->id}/verify", ['decision' => 'verified'])
        ->assertForbidden();
});

test('an already decided attendance can not be re-decided', function (): void {
    $attendance = Attendance::factory()->verified()->create(['placement_id' => $this->placement->id]);

    // Re-deciding would quietly rewrite the record an assessment rests on.
    $this->actingAs($this->teacher->user)
        ->put("/attendances/{$attendance->id}/verify", ['decision' => 'rejected', 'note' => 'berubah pikiran'])
        ->assertForbidden();
});

test('the nightly job marks missing weekday attendance as absent', function (): void {
    $monday = CarbonImmutable::parse('2026-07-13');

    (new MarkAbsentStudentsJob($monday->toDateString()))->handle();

    $attendance = Attendance::firstOrFail();

    expect($attendance->status)->toBe(AttendanceStatus::Absent)
        // The system decided this, so it is not left in anyone's queue.
        ->and($attendance->verification_status)->toBe(VerificationStatus::Verified);
});

test('the nightly job never overwrites an existing record', function (): void {
    $monday = CarbonImmutable::parse('2026-07-13');

    Attendance::factory()->permit()->onDate($monday->toDateString())
        ->create(['placement_id' => $this->placement->id]);

    (new MarkAbsentStudentsJob($monday->toDateString()))->handle();

    expect(Attendance::count())->toBe(1)
        ->and(Attendance::firstOrFail()->status)->toBe(AttendanceStatus::Permit);
});

test('the nightly job skips weekends', function (): void {
    // 2026-07-18 is a Saturday. A weekend is not an absence.
    (new MarkAbsentStudentsJob('2026-07-18'))->handle();

    expect(Attendance::count())->toBe(0);
});

test('the nightly job ignores placements that are not active', function (): void {
    Placement::query()->update(['status' => 'draft']);

    (new MarkAbsentStudentsJob('2026-07-13'))->handle();

    expect(Attendance::count())->toBe(0);
});

test('attendance photos are not readable by unrelated users', function (): void {
    $this->actingAs($this->student->user)->post('/attendances/check-in', payload());
    $path = Attendance::firstOrFail()->check_in_photo_path;

    // These are photographs of minors at work: a guessable URL must not be enough.
    $this->actingAs(Teacher::factory()->create()->user)
        ->get('/attendances/photo?path='.urlencode($path))
        ->assertForbidden();

    $this->actingAs($this->teacher->user)
        ->get('/attendances/photo?path='.urlencode($path))
        ->assertOk();
});

test('a siswa sees only their own attendance', function (): void {
    Attendance::factory()->create(['placement_id' => $this->placement->id]);
    Attendance::factory()->count(3)->create();

    $this->actingAs($this->student->user)
        ->get('/attendances')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('attendances.data', 1));
});

test('a guru sees only attendance from their own supervision', function (): void {
    Attendance::factory()->count(2)->create(['placement_id' => $this->placement->id]);
    Attendance::factory()->count(3)->create();

    $this->actingAs($this->teacher->user)
        ->get('/attendances')
        ->assertInertia(fn ($page) => $page->has('attendances.data', 2));
});

test('an admin sees every attendance', function (): void {
    Attendance::factory()->count(4)->create();

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/attendances')
        ->assertInertia(fn ($page) => $page->has('attendances.data', 4));
});
