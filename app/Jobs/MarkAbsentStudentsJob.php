<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AttendanceStatus;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\Placement;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Marks students with no attendance record for a working day as `absent`.
 *
 * Without this, "no record" and "did not show up" look identical, and a student
 * could avoid ever being marked absent simply by not touching the app.
 */
class MarkAbsentStudentsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $forDate = null) {}

    public function handle(): void
    {
        $date = $this->forDate === null
            ? CarbonImmutable::now()->startOfDay()
            : CarbonImmutable::parse($this->forDate)->startOfDay();

        // Weekends are not absences. Public holidays are not modelled yet — until they
        // are, an admin has to correct those by hand.
        if ($date->isWeekend()) {
            return;
        }

        Placement::query()
            ->active()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->whereDoesntHave('attendances', fn ($query) => $query->whereDate('date', $date))
            ->chunkById(200, function ($placements) use ($date): void {
                $now = CarbonImmutable::now();

                $rows = $placements->map(fn (Placement $placement): array => [
                    'id' => (string) Str::uuid7(),
                    'placement_id' => $placement->id,
                    'date' => $date->toDateString(),
                    'status' => AttendanceStatus::Absent->value,
                    // The system decided this, not a supervisor, so it is not left
                    // sitting in anyone's verification queue.
                    'verification_status' => VerificationStatus::Verified->value,
                    'verified_at' => $now,
                    'notes' => 'Ditandai otomatis oleh sistem: tidak ada absensi pada hari kerja ini.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows === []) {
                    return;
                }

                // insertOrIgnore, not insert: a student checking in while this job runs
                // must not cause the whole chunk to fail on the unique index.
                DB::table('attendances')->insertOrIgnore($rows);
            });
    }
}
