<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlacementStatus;
use App\Models\Company;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

/**
 * Demo placements for local development.
 *
 * Honours the same rules the service enforces — one active placement per student,
 * and never more active placements than a company's quota — so the seeded database
 * is a state the application could actually have produced.
 */
class PlacementSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = Teacher::all();
        $companies = Company::where('is_active', true)->get();

        if ($teachers->isEmpty() || $companies->isEmpty()) {
            $this->command->warn('Placement seeder skipped: no teachers or active companies.');

            return;
        }

        $students = Student::inRandomOrder()->limit(30)->get();
        $usedSlots = [];

        foreach ($students as $index => $student) {
            $company = $companies->firstWhere(
                fn (Company $c): bool => ($usedSlots[$c->id] ?? 0) < $c->quota
            );

            if ($company === null) {
                break;
            }

            // Roughly: half active, then a mix of draft, completed and cancelled so
            // every status and filter has something to show.
            $status = match ($index % 4) {
                0, 1 => PlacementStatus::Active,
                2 => PlacementStatus::Draft,
                default => PlacementStatus::Completed,
            };

            $placement = Placement::factory()
                ->state([
                    'student_id' => $student->id,
                    'company_id' => $company->id,
                    'teacher_id' => $teachers->random()->id,
                    'status' => $status,
                ])
                ->create();

            // Only an active placement holds a quota slot.
            if ($placement->status->occupiesSlot()) {
                $usedSlots[$company->id] = ($usedSlots[$company->id] ?? 0) + 1;
            }
        }

        $counts = Placement::query()
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status');

        $this->command->info(sprintf(
            'Penempatan: %d aktif, %d draft, %d selesai, %d total.',
            $counts[PlacementStatus::Active->value] ?? 0,
            $counts[PlacementStatus::Draft->value] ?? 0,
            $counts[PlacementStatus::Completed->value] ?? 0,
            Placement::count(),
        ));
    }
}
