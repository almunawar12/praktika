<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Monitoring;
use App\Models\Placement;
use App\Services\MonitoringService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo visit reports for local development.
 *
 * Leaves some placements unvisited on purpose, so the teacher dashboard's
 * "Perlu Dikunjungi" tile and the needingVisit query have something to report.
 */
class MonitoringSeeder extends Seeder
{
    public function run(): void
    {
        $placements = Placement::query()
            ->whereIn('status', array_column(MonitoringService::VISITABLE, 'value'))
            ->with('teacher')
            ->get();

        if ($placements->isEmpty()) {
            $this->command->warn('Monitoring seeder skipped: no visitable placements.');

            return;
        }

        foreach ($placements as $index => $placement) {
            // Roughly a third get no visit at all.
            if ($index % 3 === 2) {
                continue;
            }

            $visits = $index % 2 === 0 ? 2 : 1;

            for ($i = 0; $i < $visits; $i++) {
                $date = CarbonImmutable::now()
                    ->subDays(7 + $i * 21)
                    ->startOfDay();

                // A visit outside the PKL period did not happen during the PKL.
                if ($date->lt($placement->start_date) || $date->gt($placement->end_date)) {
                    continue;
                }

                Monitoring::factory()
                    ->when($i === 0, fn ($factory) => $factory->withLocation())
                    ->onDate($date->toDateString())
                    ->create([
                        'placement_id' => $placement->id,
                        'teacher_id' => $placement->teacher_id,
                    ]);
            }
        }

        $this->command->info(sprintf(
            'Monitoring: %d kunjungan pada %d penempatan (%d belum pernah dikunjungi).',
            Monitoring::count(),
            Monitoring::distinct('placement_id')->count('placement_id'),
            $placements->count() - Monitoring::distinct('placement_id')->count('placement_id'),
        ));
    }
}
