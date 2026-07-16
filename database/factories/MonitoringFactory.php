<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Monitoring;
use App\Models\Placement;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitoring>
 */
class MonitoringFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'placement_id' => Placement::factory()->active(),
            'teacher_id' => Teacher::factory(),
            'visit_date' => CarbonImmutable::parse(fake()->dateTimeBetween('-30 days', 'now'))
                ->toDateString(),
            'note' => fake()->paragraph(),
            'recommendation' => fake()->boolean(70) ? fake()->sentence() : null,
            // Location is optional evidence — many visits are filed without it.
            'latitude' => null,
            'longitude' => null,
        ];
    }

    public function withLocation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'latitude' => fake()->latitude(-7.0, -6.8),
            'longitude' => fake()->longitude(107.5, 107.7),
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes): array => ['visit_date' => $date]);
    }
}
