<?php

declare(strict_types=1);

namespace App\Exports;

use App\DataTransferObjects\ReportDataset;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Renders a ReportDataset as an XLSX sheet, streamed straight to the person who
 * asked for it — nothing is written to disk.
 */
final class ReportSpreadsheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private readonly ReportDataset $dataset) {}

    /**
     * @return array<int, array<int, string|int|float>>
     */
    public function array(): array
    {
        return $this->dataset->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->dataset->columns;
    }

    public function title(): string
    {
        return $this->dataset->title;
    }
}
