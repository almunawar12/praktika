<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Journal>
 */
class JournalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'placement_id' => Placement::factory()->active(),
            'date' => CarbonImmutable::parse(fake()->dateTimeBetween('-30 days', 'now'))->toDateString(),
            'activity' => fake()->paragraph(),
            'work_result' => fake()->paragraph(),
            'obstacle' => fake()->boolean(40) ? fake()->sentence() : null,
            'status' => ReviewStatus::Pending,
            'industry_status' => ReviewStatus::Pending,
            'teacher_status' => ReviewStatus::Pending,
        ];
    }

    /**
     * Approved by industry, still waiting on the teacher.
     */
    public function industryApproved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'industry_status' => ReviewStatus::Approved,
            // A decision must name its reviewer — the DB CHECK requires it.
            'industry_reviewed_by' => User::factory(),
            'industry_reviewed_at' => CarbonImmutable::now(),
        ]);
    }

    public function industryRejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Rejected,
            'industry_status' => ReviewStatus::Rejected,
            'industry_reviewed_by' => User::factory(),
            'industry_reviewed_at' => CarbonImmutable::now(),
            'industry_note' => 'Uraian kegiatan terlalu singkat.',
        ]);
    }

    /**
     * Fully approved: both stages, in order.
     */
    public function approved(): static
    {
        return $this->industryApproved()->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Approved,
            'teacher_status' => ReviewStatus::Approved,
            'teacher_reviewed_by' => User::factory(),
            'teacher_reviewed_at' => CarbonImmutable::now(),
        ]);
    }

    public function teacherRejected(): static
    {
        return $this->industryApproved()->state(fn (array $attributes): array => [
            'status' => ReviewStatus::Rejected,
            'teacher_status' => ReviewStatus::Rejected,
            'teacher_reviewed_by' => User::factory(),
            'teacher_reviewed_at' => CarbonImmutable::now(),
            'teacher_note' => 'Hasil pekerjaan belum sesuai kompetensi.',
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes): array => ['date' => $date]);
    }
}
