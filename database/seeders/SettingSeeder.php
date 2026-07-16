<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

/**
 * Runs in production too: the assessment weights are not demo data, they are the
 * formula the school's scores depend on (ADR-03).
 *
 * Idempotent — firstOrCreate never overwrites a weight a school has changed.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [SettingService::WEIGHT_INDUSTRY, 0.60, 'assessment'],
            [SettingService::WEIGHT_TEACHER, 0.40, 'assessment'],
        ];

        foreach ($defaults as [$key, $value, $group]) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }

        app(SettingService::class)->forget();
    }
}
