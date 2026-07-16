<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => Journal::class,
            'attachable_id' => Journal::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'attachments/seed/'.fake()->uuid().'.jpg',
            'original_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 2_000_000),
            'caption' => fake()->boolean(60) ? fake()->sentence(4) : null,
        ];
    }
}
