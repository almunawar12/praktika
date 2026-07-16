<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Enums\VerificationStatus;
use App\Models\Attendance;
use App\Models\Placement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = CarbonImmutable::parse(fake()->dateTimeBetween('-30 days', 'now'))->startOfDay();
        $checkIn = $date->setTime(7, fake()->numberBetween(0, 45));

        return [
            'placement_id' => Placement::factory()->active(),
            'date' => $date->toDateString(),
            'status' => AttendanceStatus::Present,
            // `present` carries evidence — the database CHECK constraint requires it,
            // so the factory must produce a row that could really exist.
            'check_in_at' => $checkIn,
            'check_in_latitude' => fake()->latitude(-7, -6),
            'check_in_longitude' => fake()->longitude(107, 108),
            'check_in_photo_path' => 'attendances/seed/check-in/'.fake()->uuid().'.jpg',
            'check_in_distance_m' => fake()->numberBetween(5, 120),
            'check_out_at' => $checkIn->setTime(16, fake()->numberBetween(0, 45)),
            'check_out_latitude' => fake()->latitude(-7, -6),
            'check_out_longitude' => fake()->longitude(107, 108),
            'check_out_photo_path' => 'attendances/seed/check-out/'.fake()->uuid().'.jpg',
            'check_out_distance_m' => fake()->numberBetween(5, 120),
            'verification_status' => VerificationStatus::Pending,
        ];
    }

    public function permit(): static
    {
        return $this->absenceState(AttendanceStatus::Permit, 'Ada keperluan keluarga.');
    }

    public function sick(): static
    {
        return $this->absenceState(AttendanceStatus::Sick, 'Demam, ada surat dokter.');
    }

    public function absent(): static
    {
        return $this->absenceState(AttendanceStatus::Absent, 'Ditandai otomatis oleh sistem.')
            ->state(fn (array $attributes): array => [
                'verification_status' => VerificationStatus::Verified,
                'verified_at' => CarbonImmutable::now(),
            ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => CarbonImmutable::now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::Rejected,
            'verified_at' => CarbonImmutable::now(),
            'verification_note' => 'Bukti tidak sesuai.',
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes): array => ['date' => $date]);
    }

    /**
     * Non-present statuses carry no check-in evidence at all.
     */
    private function absenceState(AttendanceStatus $status, string $notes): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'notes' => $notes,
            'check_in_at' => null,
            'check_in_latitude' => null,
            'check_in_longitude' => null,
            'check_in_photo_path' => null,
            'check_in_distance_m' => null,
            'check_out_at' => null,
            'check_out_latitude' => null,
            'check_out_longitude' => null,
            'check_out_photo_path' => null,
            'check_out_distance_m' => null,
        ]);
    }
}
