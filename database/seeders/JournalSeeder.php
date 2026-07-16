<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ReviewStatus;
use App\Models\CompanySupervisor;
use App\Models\Journal;
use App\Models\Placement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo journals for local development.
 *
 * Only produces states the application could really reach: one journal per day,
 * weekdays only, and a teacher decision never without an industry approval first.
 */
class JournalSeeder extends Seeder
{
    public function run(): void
    {
        $placements = Placement::query()->active()->with(['company', 'teacher'])->get();

        if ($placements->isEmpty()) {
            $this->command->warn('Journal seeder skipped: no active placements.');

            return;
        }

        foreach ($placements as $placement) {
            $this->seedFor($placement);
        }

        $this->command->info(sprintf(
            'Jurnal: %d total — %d menunggu industri, %d menunggu guru, %d disetujui, %d ditolak.',
            Journal::count(),
            Journal::query()->pendingForIndustry()->count(),
            Journal::query()->pendingForTeacher()->count(),
            Journal::where('status', ReviewStatus::Approved)->count(),
            Journal::where('status', ReviewStatus::Rejected)->count(),
        ));
    }

    private function seedFor(Placement $placement): void
    {
        $supervisor = CompanySupervisor::where('company_id', $placement->company_id)->first();
        $teacherUser = $placement->teacher->user_id;

        $date = CarbonImmutable::now()->subDays(10);
        $today = CarbonImmutable::now()->startOfDay();
        $index = 0;

        while ($date->lte($today)) {
            if ($date->isWeekend() || $date->lt($placement->start_date) || $date->gt($placement->end_date)) {
                $date = $date->addDay();

                continue;
            }

            $factory = Journal::factory()->onDate($date->toDateString());

            // A spread across every state the review flow can produce.
            $factory = match ($index % 5) {
                0 => $factory,
                1 => $supervisor === null
                    ? $factory
                    : $factory->state([
                        'industry_status' => ReviewStatus::Approved,
                        'industry_reviewed_by' => $supervisor->user_id,
                        'industry_reviewed_at' => CarbonImmutable::now(),
                    ]),
                2 => $supervisor === null
                    ? $factory
                    : $factory->state([
                        'status' => ReviewStatus::Approved,
                        'industry_status' => ReviewStatus::Approved,
                        'industry_reviewed_by' => $supervisor->user_id,
                        'industry_reviewed_at' => CarbonImmutable::now(),
                        'teacher_status' => ReviewStatus::Approved,
                        'teacher_reviewed_by' => $teacherUser,
                        'teacher_reviewed_at' => CarbonImmutable::now(),
                    ]),
                3 => $supervisor === null
                    ? $factory
                    : $factory->state([
                        'status' => ReviewStatus::Rejected,
                        'industry_status' => ReviewStatus::Rejected,
                        'industry_reviewed_by' => $supervisor->user_id,
                        'industry_reviewed_at' => CarbonImmutable::now(),
                        'industry_note' => 'Uraian kegiatan terlalu singkat, mohon diperjelas.',
                    ]),
                default => $factory,
            };

            $factory->create(['placement_id' => $placement->id]);

            $date = $date->addDay();
            $index++;
        }
    }
}
