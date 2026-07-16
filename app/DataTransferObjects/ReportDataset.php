<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\ReportType;

/**
 * The rendered content of a report, independent of output format.
 *
 * Built once by ReportService, then handed to whichever renderer the owner
 * asked for — the Blade PDF view and the Excel export read the same shape, so
 * the two formats can never drift apart.
 */
final readonly class ReportDataset
{
    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, string|int|float>>  $rows
     */
    public function __construct(
        public ReportType $type,
        public string $title,
        public string $scope,
        public array $columns,
        public array $rows,
        public string $requestedBy,
        public string $generatedAt,
    ) {}
}
