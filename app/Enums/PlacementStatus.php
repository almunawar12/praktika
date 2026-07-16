<?php

declare(strict_types=1);

namespace App\Enums;

enum PlacementStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Aktif',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * The states this one may move to.
     *
     * `completed` and `cancelled` are terminal: a finished PKL is a record, and
     * re-opening one would silently change what its attendance and assessments mean.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Cancelled],
            self::Active => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Whether the placement still holds one of the company's quota slots and the
     * student's single active-placement slot.
     */
    public function occupiesSlot(): bool
    {
        return $this === self::Active;
    }

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Active;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
