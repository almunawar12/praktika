<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');

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

function newJournal(array $overrides = []): array
{
    return array_merge([
        'date' => CarbonImmutable::now()->toDateString(),
        'activity' => 'Membantu tim QA melakukan regression testing modul login.',
        'work_result' => 'Menemukan 3 bug dan mendokumentasikannya di issue tracker.',
        'obstacle' => 'Akses staging sempat terputus.',
    ], $overrides);
}

test('a student writes a journal and it goes to industry first', function (): void {
    $this->actingAs($this->student->user)
        ->post('/journals', newJournal())
        ->assertRedirect();

    $journal = Journal::firstOrFail();

    expect($journal->placement_id)->toBe($this->placement->id)
        ->and($journal->status->value)->toBe('pending')
        ->and($journal->industry_status->value)->toBe('pending');
});

test('only one journal per day', function (): void {
    $this->actingAs($this->student->user)->post('/journals', newJournal());

    $this->actingAs($this->student->user)
        ->post('/journals', newJournal())
        ->assertSessionHas('error');

    expect(Journal::count())->toBe(1);
});

test('the database refuses a second journal for the same day', function (): void {
    Journal::factory()->onDate('2026-07-10')->create(['placement_id' => $this->placement->id]);

    // Two submits both pass the service check; the unique index stops the second.
    expect(fn () => Journal::factory()->onDate('2026-07-10')->create([
        'placement_id' => $this->placement->id,
    ]))->toThrow(QueryException::class);
});

test('a soft-deleted journal frees the day again', function (): void {
    $journal = Journal::factory()->onDate('2026-07-10')->create(['placement_id' => $this->placement->id]);
    $journal->delete();

    // The unique index is partial, so deleting must not reserve the date forever.
    Journal::factory()->onDate('2026-07-10')->create(['placement_id' => $this->placement->id]);

    expect(Journal::where('date', '2026-07-10')->count())->toBe(1);
});

test('a journal can not be written for a day that has not happened', function (): void {
    $this->actingAs($this->student->user)
        ->post('/journals', newJournal(['date' => CarbonImmutable::now()->addDay()->toDateString()]))
        ->assertSessionHasErrors('date');
});

test('a journal can not be written outside the placement period', function (): void {
    $this->actingAs($this->student->user)
        ->post('/journals', newJournal([
            'date' => CarbonImmutable::now()->subMonths(3)->toDateString(),
        ]))
        ->assertSessionHas('error');

    expect(Journal::count())->toBe(0);
});

test('a student without an active placement can not write a journal', function (): void {
    $orphan = Student::factory()->create();

    $this->actingAs($orphan->user)
        ->post('/journals', newJournal())
        ->assertSessionHas('error');
});

test('activity and work result must be substantive', function (): void {
    $this->actingAs($this->student->user)
        ->post('/journals', newJournal(['activity' => 'kerja', 'work_result' => 'ok']))
        ->assertSessionHasErrors(['activity', 'work_result']);
});

test('documentation is attached to the journal', function (): void {
    $this->actingAs($this->student->user)
        ->post('/journals', newJournal([
            'attachments' => [
                UploadedFile::fake()->image('bukti-1.jpg'),
                UploadedFile::fake()->image('bukti-2.png'),
            ],
        ]))
        ->assertSessionHasNoErrors();

    $journal = Journal::firstOrFail();

    expect($journal->attachments)->toHaveCount(2);
    Storage::disk('local')->assertExists($journal->attachments->first()->path);
});

test('an executable upload is refused', function (): void {
    $this->actingAs($this->student->user)
        ->post('/journals', newJournal([
            'attachments' => [UploadedFile::fake()->create('shell.php', 10, 'application/x-php')],
        ]))
        ->assertSessionHasErrors('attachments.0');

    expect(Journal::count())->toBe(0);
});

test('attachments are readable through the parent, not by guessing', function (): void {
    $this->actingAs($this->student->user)
        ->post('/journals', newJournal(['attachments' => [UploadedFile::fake()->image('bukti.jpg')]]));

    $attachment = Attachment::firstOrFail();

    // The supervising teacher and the owning student may read it.
    $this->actingAs($this->teacher->user)->get("/attachments/{$attachment->id}")->assertOk();
    $this->actingAs($this->student->user)->get("/attachments/{$attachment->id}")->assertOk();

    // An unrelated teacher may not.
    $this->actingAs(Teacher::factory()->create()->user)
        ->get("/attachments/{$attachment->id}")
        ->assertForbidden();
});

test('deleting an attachment removes the file too', function (): void {
    $this->actingAs($this->student->user)
        ->post('/journals', newJournal(['attachments' => [UploadedFile::fake()->image('bukti.jpg')]]));

    $attachment = Attachment::firstOrFail();
    $path = $attachment->path;

    $this->actingAs($this->student->user)
        ->delete("/attachments/{$attachment->id}")
        ->assertRedirect();

    Storage::disk('local')->assertMissing($path);
    expect(Attachment::count())->toBe(0);
});

test('journals are scoped to what each role may see', function (): void {
    // Distinct dates: journals are unique per placement per day, and the factory's
    // default date is random over a 30-day window — two draws on one placement
    // collide often enough to fail the suite roughly one run in thirty.
    Journal::factory()->count(2)->sequence(
        ['date' => CarbonImmutable::now()->subDay()->toDateString()],
        ['date' => CarbonImmutable::now()->subDays(2)->toDateString()],
    )->create(['placement_id' => $this->placement->id]);

    // These each get their own placement, so their random dates cannot collide.
    Journal::factory()->count(3)->create();

    foreach ([$this->student->user, $this->teacher->user, $this->supervisor->user] as $viewer) {
        $this->actingAs($viewer)
            ->get('/journals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('journals.data', 2));
    }

    $this->actingAs(userWithRole(RoleName::AdminPkl))
        ->get('/journals')
        ->assertInertia(fn ($page) => $page->has('journals.data', 5));
});
