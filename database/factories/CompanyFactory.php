<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'business_field' => fake()->randomElement([
                'Teknologi Informasi',
                'Manufaktur',
                'Perbankan',
                'Retail',
                'Konstruksi',
                'Telekomunikasi',
            ]),
            'address' => fake()->address(),
            'pic_name' => fake()->name(),
            'pic_phone' => fake()->numerify('08##########'),
            'quota' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
