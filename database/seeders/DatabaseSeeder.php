<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            // Not demo data: the assessment weights are the formula real scores
            // depend on (ADR-03).
            SettingSeeder::class,
            UserSeeder::class,
            // Not demo data either: a real school needs a current academic year from
            // day one, or every class and placement starts out unscoped.
            AcademicYearSeeder::class,
        ]);

        // Demo data invents people, companies and placements — never in production.
        if (! app()->isProduction()) {
            $this->call([
                MasterDataSeeder::class,
                PlacementSeeder::class,
                AttendanceSeeder::class,
                JournalSeeder::class,
                MonitoringSeeder::class,
                AssessmentSeeder::class,
            ]);
        }
    }
}
