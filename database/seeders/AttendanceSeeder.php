<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\Placement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo attendance for local development.
 *
 * Produces only states the application could really have produced: one record per
 * placement per day, weekdays only, and evidence attached to every `present` row.
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $placements = Placement::query()->active()->get();

        if ($placements->isEmpty()) {
            $this->command->warn('Attendance seeder skipped: no active placements.');

            return;
        }

        foreach ($placements as $placement) {
            $this->seedFor($placement);
        }

        $this->command->info(sprintf(
            'Absensi: %d hadir, %d izin, %d sakit, %d alpha (%d menunggu verifikasi).',
            Attendance::where('status', AttendanceStatus::Present)->count(),
            Attendance::where('status', AttendanceStatus::Permit)->count(),
            Attendance::where('status', AttendanceStatus::Sick)->count(),
            Attendance::where('status', AttendanceStatus::Absent)->count(),
            Attendance::where('verification_status', VerificationStatus::Pending)->count(),
        ));
    }

    private function seedFor(Placement $placement): void
    {
        $date = CarbonImmutable::now()->subDays(14);
        $today = CarbonImmutable::now()->startOfDay();
        $index = 0;

        while ($date->lte($today)) {
            // Weekends produce no attendance at all, matching MarkAbsentStudentsJob.
            if ($date->isWeekend() || $date->lt($placement->start_date) || $date->gt($placement->end_date)) {
                $date = $date->addDay();

                continue;
            }

            $factory = Attendance::factory()->onDate($date->toDateString());

            $factory = match ($index % 7) {
                5 => $factory->permit(),
                6 => $factory->sick(),
                default => $factory,
            };

            // Older records are already decided; the last few days stay pending so the
            // verification queue has something in it.
            $factory = $date->lt($today->subDays(3))
                ? $factory->verified()
                : $factory;

            $factory->create(['placement_id' => $placement->id]);

            $date = $date->addDay();
            $index++;
        }
    }
}
