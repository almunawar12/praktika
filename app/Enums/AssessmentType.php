<?php

declare(strict_types=1);

namespace App\Enums;

enum AssessmentType: string
{
    case Industry = 'industry';
    case Teacher = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::Industry => 'Penilaian Industri',
            self::Teacher => 'Penilaian Guru',
        };
    }

    /**
     * The criteria this side scores (PRD § 4, Modul Penilaian).
     *
     * @return array<int, AssessmentCriteria>
     */
    public function criteria(): array
    {
        return array_values(array_filter(
            AssessmentCriteria::cases(),
            fn (AssessmentCriteria $criteria): bool => $criteria->type() === $this,
        ));
    }

    /**
     * The permission that lets someone submit this side.
     */
    public function permission(): string
    {
        return match ($this) {
            self::Industry => 'assessments.submitAsIndustry',
            self::Teacher => 'assessments.submitAsTeacher',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
