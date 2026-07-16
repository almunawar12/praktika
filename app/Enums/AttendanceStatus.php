<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Permit = 'permit';
    case Sick = 'sick';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Permit => 'Izin',
            self::Sick => 'Sakit',
            self::Absent => 'Alpha',
        };
    }

    /**
     * Statuses a student may claim for themselves.
     *
     * `present` comes from check-in and `absent` from the nightly job — neither is
     * something a student picks from a list.
     *
     * @return array<int, self>
     */
    public static function requestable(): array
    {
        return [self::Permit, self::Sick];
    }

    public function isRequestable(): bool
    {
        return in_array($this, self::requestable(), true);
    }

    /**
     * Whether this status involves physically being at the company.
     */
    public function requiresCheckIn(): bool
    {
        return $this === self::Present;
    }

    /**
     * Counts toward attendance for the teacher's assessment.
     */
    public function countsAsAttending(): bool
    {
        return $this === self::Present;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
