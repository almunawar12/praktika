<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();
        $phone = fake()->numerify('08##########');

        return [
            // A student without a login can not check in or file a journal, so the
            // factory mirrors StudentService and always produces the account too.
            'user_id' => User::factory()
                ->withRole(RoleName::Siswa)
                ->state(['name' => $name, 'phone' => $phone]),
            'class_id' => SchoolClass::factory(),
            'nis' => fake()->unique()->numerify('##########'),
            'nisn' => fake()->unique()->numerify('##########'),
            'name' => $name,
            'phone' => $phone,
        ];
    }
}
