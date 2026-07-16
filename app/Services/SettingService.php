<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    public const WEIGHT_INDUSTRY = 'assessment.weight.industry';

    public const WEIGHT_TEACHER = 'assessment.weight.teacher';

    private const CACHE_KEY = 'praktika.settings';

    private const DEFAULTS = [
        // ADR-03: industry sees the daily work, so it carries the larger share.
        self::WEIGHT_INDUSTRY => 0.60,
        self::WEIGHT_TEACHER => 0.40,
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    public function set(string $key, mixed $value, string $group = 'general'): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);

        $this->forget();
    }

    /**
     * The assessment weights, as a pair that always sums to 1.
     *
     * @return array{industry: float, teacher: float}
     */
    public function weights(): array
    {
        $industry = (float) $this->get(self::WEIGHT_INDUSTRY);
        $teacher = (float) $this->get(self::WEIGHT_TEACHER);

        // A pair that does not split 100% would produce a final score that can not be
        // reproduced from its own inputs. UpdateSettingRequest validates this, and
        // final_scores_weights_check refuses it at the database; falling back here
        // means a hand-edited row can not silently corrupt every calculation.
        if (abs($industry + $teacher - 1.0) > 0.0001) {
            return [
                'industry' => self::DEFAULTS[self::WEIGHT_INDUSTRY],
                'teacher' => self::DEFAULTS[self::WEIGHT_TEACHER],
            ];
        }

        return ['industry' => $industry, 'teacher' => $teacher];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
