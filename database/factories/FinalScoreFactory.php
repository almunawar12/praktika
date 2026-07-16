<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FinalScore;
use App\Models\Placement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinalScore>
 */
class FinalScoreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $industry = fake()->randomFloat(2, 60, 100);
        $teacher = fake()->randomFloat(2, 60, 100);
        $final = round($industry * 0.6 + $teacher * 0.4, 2);

        return [
            'placement_id' => Placement::factory()->completed(),
            'industry_score' => $industry,
            'teacher_score' => $teacher,
            // Must sum to 1 — final_scores_weights_check refuses anything else.
            'industry_weight' => 0.6,
            'teacher_weight' => 0.4,
            'final_score' => $final,
            'grade' => match (true) {
                $final >= 90 => 'A',
                $final >= 80 => 'B',
                $final >= 70 => 'C',
                $final >= 60 => 'D',
                default => 'E',
            },
            'published_at' => null,
            'published_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => CarbonImmutable::now(),
            // A publication must name who published it.
            'published_by' => User::factory(),
        ]);
    }
}
