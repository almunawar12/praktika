<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\Monitoring;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');

    $this->company = Company::factory()->create(['quota' => 10]);
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

function visitPayload(Placement $placement, array $overrides = []): array
{
    return array_merge([
        'placement_id' => $placement->id,
        'visit_date' => CarbonImmutable::now()->toDateString(),
        'note' => 'Siswa aktif membantu tim produksi dan diterima baik oleh pembimbing.',
        'recommendation' => 'Tingkatkan ketelitian pada dokumentasi pekerjaan.',
    ], $overrides);
}

test('a teacher records a visit for their own supervision', function (): void {
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($this->placement))
        ->assertRedirect();

    $monitoring = Monitoring::firstOrFail();

    expect($monitoring->placement_id)->toBe($this->placement->id)
        ->and($monitoring->teacher_id)->toBe($this->teacher->id);
});

test('a teacher can not record a visit for another teachers placement', function (): void {
    $other = Placement::factory()->active()->create();

    // Holding monitorings.create is not enough: the placement must be theirs.
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($other))
        ->assertForbidden();

    expect(Monitoring::count())->toBe(0);
});

test('visits can be recorded for a completed placement', function (): void {
    $placement = Placement::factory()->completed()->create([
        'teacher_id' => $this->teacher->id,
        'start_date' => CarbonImmutable::now()->subMonths(4)->toDateString(),
        'end_date' => CarbonImmutable::now()->subMonth()->toDateString(),
    ]);

    // A teacher who forgot to file before the PKL ended must still be able to —
    // the final report is exactly when it is noticed.
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($placement, [
            'visit_date' => CarbonImmutable::now()->subMonths(2)->toDateString(),
        ]))
        ->assertSessionHasNoErrors();

    expect(Monitoring::count())->toBe(1);
});

test('a visit can not be recorded against a draft or cancelled placement', function (string $status): void {
    // Draft is PlacementFactory's default, so the status is set directly rather
    // than through a state that does not exist.
    $placement = Placement::factory()->create([
        'teacher_id' => $this->teacher->id,
        'status' => $status,
        'start_date' => CarbonImmutable::now()->subMonth()->toDateString(),
        'end_date' => CarbonImmutable::now()->addMonth()->toDateString(),
    ]);

    // There was no PKL to visit.
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($placement))
        ->assertSessionHas('error');

    expect(Monitoring::count())->toBe(0);
})->with(['draft', 'cancelled']);

test('a visit date outside the placement period is refused', function (): void {
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($this->placement, [
            'visit_date' => CarbonImmutable::now()->subMonths(6)->toDateString(),
        ]))
        ->assertSessionHas('error');

    expect(Monitoring::count())->toBe(0);
});

test('a visit can not be dated in the future', function (): void {
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($this->placement, [
            'visit_date' => CarbonImmutable::now()->addWeek()->toDateString(),
        ]))
        ->assertSessionHasErrors('visit_date');
});

test('location is optional and never blocks filing', function (): void {
    // A teacher filing from school, or with location denied, must still succeed.
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($this->placement))
        ->assertSessionHasNoErrors();

    expect(Monitoring::firstOrFail()->hasLocation())->toBeFalse();
});

test('location is recorded when the browser supplies it', function (): void {
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($this->placement, [
            'latitude' => -6.9147440,
            'longitude' => 107.6098100,
        ]))
        ->assertSessionHasNoErrors();

    expect(Monitoring::firstOrFail()->hasLocation())->toBeTrue();
});

test('half a coordinate pair is refused', function (): void {
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($this->placement, ['latitude' => -6.91]))
        ->assertSessionHasErrors('longitude');
});

test('the database refuses half a coordinate pair too', function (): void {
    expect(fn () => DB::table('monitorings')->insert([
        'id' => Str::uuid7()->toString(),
        'placement_id' => $this->placement->id,
        'teacher_id' => $this->teacher->id,
        'visit_date' => CarbonImmutable::now()->toDateString(),
        'note' => 'x',
        'latitude' => -6.91,
        'longitude' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('only the author may edit or delete a visit report', function (): void {
    $monitoring = Monitoring::factory()->create([
        'placement_id' => $this->placement->id,
        'teacher_id' => $this->teacher->id,
    ]);

    $this->actingAs($this->teacher->user)
        ->put("/monitorings/{$monitoring->id}", [
            'visit_date' => $monitoring->visit_date->toDateString(),
            'note' => 'Catatan yang sudah diperbarui setelah kunjungan kedua.',
        ])
        ->assertRedirect();

    // A visit report is a first-hand account: another teacher editing it would be
    // putting words in someone else's mouth.
    $stranger = Teacher::factory()->create();

    $this->actingAs($stranger->user)
        ->put("/monitorings/{$monitoring->id}", [
            'visit_date' => $monitoring->visit_date->toDateString(),
            'note' => 'Saya ubah laporan orang lain.',
        ])
        ->assertForbidden();

    $this->actingAs($stranger->user)
        ->delete("/monitorings/{$monitoring->id}")
        ->assertForbidden();
});

test('the placement on a report can not be moved', function (): void {
    $monitoring = Monitoring::factory()->create([
        'placement_id' => $this->placement->id,
        'teacher_id' => $this->teacher->id,
    ]);

    $this->actingAs($this->teacher->user)
        ->put("/monitorings/{$monitoring->id}", [
            'placement_id' => Placement::factory()->active()->create()->id,
            'visit_date' => $monitoring->visit_date->toDateString(),
            'note' => 'Mencoba memindahkan laporan ke PKL lain.',
        ])
        ->assertSessionHasErrors('placement_id');
});

test('a student sees visit reports about their own PKL but can not write one', function (): void {
    Monitoring::factory()->create([
        'placement_id' => $this->placement->id,
        'teacher_id' => $this->teacher->id,
    ]);
    Monitoring::factory()->count(3)->create();

    $this->actingAs($this->student->user)
        ->get('/monitorings')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('monitorings.data', 1));

    $this->actingAs($this->student->user)
        ->post('/monitorings', visitPayload($this->placement))
        ->assertForbidden();
});

test('a pembimbing industri can not read visit reports at all', function (): void {
    $supervisor = CompanySupervisor::factory()->create(['company_id' => $this->company->id]);
    Monitoring::factory()->create([
        'placement_id' => $this->placement->id,
        'teacher_id' => $this->teacher->id,
    ]);

    // A visit report is the school's internal assessment of how the company is
    // hosting the student — not the company's to read.
    $this->actingAs($supervisor->user)->get('/monitorings')->assertForbidden();
});

test('a guru sees only their own supervision', function (): void {
    Monitoring::factory()->count(2)->create([
        'placement_id' => $this->placement->id,
        'teacher_id' => $this->teacher->id,
    ]);
    Monitoring::factory()->count(3)->create();

    $this->actingAs($this->teacher->user)
        ->get('/monitorings')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('monitorings.data', 2));

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/monitorings')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('monitorings.data', 5));
});

test('documentation is attached to the visit', function (): void {
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($this->placement, [
            'attachments' => [UploadedFile::fake()->image('kunjungan.jpg')],
        ]))
        ->assertSessionHasNoErrors();

    $monitoring = Monitoring::firstOrFail();

    expect($monitoring->attachments)->toHaveCount(1);
    Storage::disk('local')->assertExists($monitoring->attachments->first()->path);
});

test('visit documentation is authorized through the report', function (): void {
    $this->actingAs($this->teacher->user)
        ->post('/monitorings', visitPayload($this->placement, [
            'attachments' => [UploadedFile::fake()->image('kunjungan.jpg')],
        ]));

    $attachment = Attachment::firstOrFail();

    $this->actingAs($this->teacher->user)->get("/attachments/{$attachment->id}")->assertOk();
    $this->actingAs($this->student->user)->get("/attachments/{$attachment->id}")->assertOk();

    $this->actingAs(Teacher::factory()->create()->user)
        ->get("/attachments/{$attachment->id}")
        ->assertForbidden();
});
