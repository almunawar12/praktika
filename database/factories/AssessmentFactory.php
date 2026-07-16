<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssessmentType;
use App\Models\Assessment;
use App\Models\Placement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'placement_id' => Placement::factory()->active(),
            'type' => AssessmentType::Industry,
            'assessor_id' => User::factory(),
            'average_score' => null,
            'note' => null,
            // Draft by default: submitting is what makes an assessment count.
            'submitted_at' => null,
        ];
    }

    public function industry(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => AssessmentType::Industry]);
    }

    public function teacher(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => AssessmentType::Teacher]);
    }

    /**
     * Submitted with a given average. Scores rows are created by
     * `withScores()` when a test needs the per-criteria detail.
     */
    public function submitted(float $average = 85.0): static
    {
        return $this->state(fn (array $attributes): array => [
            'average_score' => $average,
            'submitted_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * Fill every criterion for this assessment's type with the same score, so the
     * stored average matches the rows behind it.
     */
    public function withScores(float $score = 85.0): static
    {
        return $this->afterCreating(function (Assessment $assessment) use ($score): void {
            foreach ($assessment->type->criteria() as $criteria) {
                $assessment->scores()->create([
                    'criteria_code' => $criteria->value,
                    'score' => $score,
                ]);
            }

            $assessment->updateQuietly(['average_score' => $score]);
        });
    }
}
