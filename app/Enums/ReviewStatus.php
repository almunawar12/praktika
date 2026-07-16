<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Used for a journal's per-stage decisions (industry, teacher) and for the final
 * status derived from them.
 */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Review',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The statuses a reviewer may choose. `pending` is a state, not a decision.
     *
     * @return array<int, self>
     */
    public static function decisions(): array
    {
        return [self::Approved, self::Rejected];
    }
}
