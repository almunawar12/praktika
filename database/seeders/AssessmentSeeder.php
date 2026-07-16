<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AssessmentType;
use App\Models\Assessment;
use App\Models\CompanySupervisor;
use App\Models\FinalScore;
use App\Models\Placement;
use App\Models\User;
use App\Services\AssessmentService;
use App\Services\ScoreCalculatorService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo assessments for local development.
 *
 * Leaves a spread of states on purpose: some placements fully scored and
 * published, some awaiting one side, some untouched — so every dashboard tile and
 * filter has something to report.
 */
class AssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $placements = Placement::query()
            ->whereIn('status', array_column(AssessmentService::ASSESSABLE, 'value'))
            ->with('teacher')
            ->get();

        if ($placements->isEmpty()) {
            $this->command->warn('Assessment seeder skipped: no assessable placements.');

            return;
        }

        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin-pkl'))->first();

        foreach ($placements as $index => $placement) {
            $supervisor = CompanySupervisor::where('company_id', $placement->company_id)->first();

            if ($supervisor === null) {
                continue;
            }

            // A quarter get nothing at all.
            if ($index % 4 === 3) {
                continue;
            }

            $this->assess($placement, AssessmentType::Industry, $supervisor->user_id, fake()->randomFloat(2, 70, 98));

            // A third stop after the industry side — the teacher has not scored yet.
            if ($index % 3 === 1) {
                continue;
            }

            $this->assess($placement, AssessmentType::Teacher, $placement->teacher->user_id, fake()->randomFloat(2, 65, 95));

            // Called explicitly because DatabaseSeeder runs with `WithoutModelEvents`,
            // which suppresses the `saved` hook AssessmentObserver rides on. In the
            // application itself the observer does this — see AssessmentFlowTest.
            app(ScoreCalculatorService::class)->recalculateIfReady($placement);

            // Publish roughly half.
            if ($index % 2 === 0 && $admin !== null) {
                FinalScore::where('placement_id', $placement->id)->update([
                    'published_at' => CarbonImmutable::now(),
                    'published_by' => $admin->id,
                ]);
            }
        }

        $this->command->info(sprintf(
            'Penilaian: %d terkirim (%d industri, %d guru) — %d nilai akhir, %d dipublikasikan.',
            Assessment::whereNotNull('submitted_at')->count(),
            Assessment::where('type', AssessmentType::Industry)->whereNotNull('submitted_at')->count(),
            Assessment::where('type', AssessmentType::Teacher)->whereNotNull('submitted_at')->count(),
            FinalScore::count(),
            FinalScore::whereNotNull('published_at')->count(),
        ));
    }

    private function assess(Placement $placement, AssessmentType $type, string $assessorId, float $average): void
    {
        $assessment = Assessment::factory()
            ->state([
                'placement_id' => $placement->id,
                'type' => $type,
                'assessor_id' => $assessorId,
                'submitted_at' => CarbonImmutable::now(),
            ])
            ->create();

        // Per-criteria rows that actually average to the stored figure, so the seeded
        // data is a state the application could really have produced.
        $scores = $this->spread($average, count($type->criteria()));

        foreach ($type->criteria() as $i => $criteria) {
            $assessment->scores()->create([
                'criteria_code' => $criteria->value,
                'score' => $scores[$i],
            ]);
        }

        $assessment->update(['average_score' => round(array_sum($scores) / count($scores), 2)]);
    }

    /**
     * Scores that vary a little but average close to the target.
     *
     * @return array<int, float>
     */
    private function spread(float $average, int $count): array
    {
        $scores = [];

        for ($i = 0; $i < $count; $i++) {
            $scores[] = min(100.0, max(0.0, round($average + fake()->randomFloat(1, -4, 4), 1)));
        }

        return $scores;
    }
}
