<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();
        $phone = fake()->numerify('08##########');

        return [
            // A teacher without a login can not supervise anything, so the factory
            // mirrors TeacherService and always produces the account too.
            'user_id' => User::factory()
                ->withRole(RoleName::GuruPembimbing)
                ->state(['name' => $name, 'phone' => $phone]),
            'nip' => fake()->unique()->numerify('##################'),
            'name' => $name,
            'phone' => $phone,
        ];
    }
}
