<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of an export. Reports are built on the queue, so the row exists
 * before the file does — the status tells the UI whether to offer a download,
 * a spinner, or a retry.
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Processing => 'Diproses',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function isDownloadable(): bool
    {
        return $this === self::Completed;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
