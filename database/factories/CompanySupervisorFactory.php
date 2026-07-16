<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySupervisor>
 */
class CompanySupervisorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();
        $phone = fake()->numerify('08##########');

        return [
            // Mirrors CompanySupervisorService: a supervisor with no login can not
            // verify or review anything, so the factory always produces the account.
            'user_id' => User::factory()
                ->withRole(RoleName::PembimbingIndustri)
                ->state(['name' => $name, 'phone' => $phone]),
            'company_id' => Company::factory(),
            'name' => $name,
            'position' => fake()->randomElement([
                'HRD', 'Supervisor Produksi', 'Team Lead', 'Manager Operasional', 'Mentor Teknis',
            ]),
            'phone' => $phone,
        ];
    }
}
