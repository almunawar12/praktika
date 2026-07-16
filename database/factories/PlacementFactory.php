<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlacementStatus;
use App\Models\Company;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Placement>
 */
class PlacementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 months', '+1 month');
        $end = (clone $start)->modify('+3 months');

        return [
            'student_id' => Student::factory(),
            'company_id' => Company::factory(),
            'teacher_id' => Teacher::factory(),
            'status' => PlacementStatus::Draft,
            'start_date' => $start,
            'end_date' => $end,
            'cancel_reason' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlacementStatus::Active,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlacementStatus::Completed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlacementStatus::Cancelled,
            'cancel_reason' => fake()->sentence(),
        ]);
    }
}
