<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Seeds the current academic year as active, idempotently, so a fresh install has
 * one from the start instead of every school class and placement starting
 * unscoped until an admin remembers to create one by hand.
 */
class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        if (AcademicYear::query()->exists()) {
            return;
        }

        $now = CarbonImmutable::now();

        // The Indonesian school year runs July–June: before July, the current year
        // started the previous July.
        $startYear = $now->month >= 7 ? $now->year : $now->year - 1;

        AcademicYear::create([
            'name' => "{$startYear}/".($startYear + 1),
            'start_date' => CarbonImmutable::create($startYear, 7, 1),
            'end_date' => CarbonImmutable::create($startYear + 1, 6, 30),
            'is_active' => true,
        ]);
    }
}
