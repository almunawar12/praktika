<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Unique per year so many years can coexist without colliding on name —
        // a real academic year always spans July of one year to June of the next.
        $year = fake()->unique()->numberBetween(2000, 2100);

        return [
            'name' => "{$year}/".($year + 1),
            'start_date' => CarbonImmutable::create($year, 7, 1),
            'end_date' => CarbonImmutable::create($year + 1, 6, 30),
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => true]);
    }
}
