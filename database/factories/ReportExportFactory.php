<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\ReportExport;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requested_by' => User::factory(),
            'type' => fake()->randomElement(ReportType::cases()),
            'format' => fake()->randomElement(ReportFormat::cases()),
            // Pending by default: the file is produced on the queue, not here.
            'status' => ReportStatus::Pending,
            'filters' => [],
            'file_path' => null,
            'file_name' => null,
            'row_count' => null,
            'completed_at' => null,
            'error' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::Completed,
            'file_path' => 'reports/'.fake()->uuid().'.pdf',
            'file_name' => 'rekap.pdf',
            'row_count' => fake()->numberBetween(1, 50),
            'completed_at' => CarbonImmutable::now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReportStatus::Failed,
            'error' => 'Contoh kegagalan.',
        ]);
    }
}
