<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * The rendered content of a "Rekap Siswa" report — one student's whole PKL,
 * combined. Unlike ReportDataset this isn't a flat table: it's sectioned
 * (identity, attendance, journal, score, monitoring), so it has its own Blade
 * view instead of sharing reports/pdf.blade.php.
 */
final readonly class StudentSummaryDataset
{
    /**
     * @param  array<int, array{visit_date: string, note: string, recommendation: ?string}>  $monitorings
     */
    public function __construct(
        public string $studentName,
        public string $nis,
        public string $className,
        public string $companyName,
        public string $teacherName,
        public string $period,
        public string $scope,
        public int $attendancePresent,
        public int $attendancePermit,
        public int $attendanceSick,
        public int $attendanceAbsent,
        public string $attendanceRate,
        public int $journalTotal,
        public int $journalApproved,
        public int $journalPending,
        public int $journalRejected,
        public ?string $industryScore,
        public ?string $teacherScore,
        public ?string $finalScore,
        public ?string $grade,
        public string $assessmentStatus,
        public array $monitorings,
        public string $requestedBy,
        public string $generatedAt,
    ) {}
}
