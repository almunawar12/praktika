<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
        };
    }

    /**
     * A rejected record is not attendance. It stays in the table — deleting it would
     * erase the fact that a claim was made and refused — but it must never be
     * counted as if the student was there.
     */
    public function countsAsAttendance(): bool
    {
        return $this !== self::Rejected;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
