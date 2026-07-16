<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportFormat: string
{
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Xlsx => 'Excel (XLSX)',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }

    /**
     * The MIME type sent on download, so the browser handles the file correctly.
     */
    public function contentType(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
