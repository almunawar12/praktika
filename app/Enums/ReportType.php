<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A report the user can export.
 *
 * Each case's value is also the second half of its permission name
 * (`reports.{value}`), so who may generate what stays defined in one place —
 * PermissionCatalog § "Reports".
 */
enum ReportType: string
{
    case Attendance = 'attendance';
    case Journal = 'journal';
    case Assessment = 'assessment';
    case Monitoring = 'monitoring';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'Rekap Absensi',
            self::Journal => 'Rekap Jurnal',
            self::Assessment => 'Rekap Nilai PKL',
            self::Monitoring => 'Rekap Monitoring',
        };
    }

    /**
     * The permission that lets someone generate this report.
     */
    public function permission(): string
    {
        return "reports.{$this->value}";
    }

    /**
     * Column headings for the exported table, in order.
     *
     * @return array<int, string>
     */
    public function columns(): array
    {
        return match ($this) {
            self::Attendance => ['Siswa', 'NIS', 'Perusahaan', 'Hadir', 'Izin', 'Sakit', 'Alpa', '% Kehadiran'],
            self::Journal => ['Siswa', 'NIS', 'Perusahaan', 'Total', 'Disetujui', 'Pending', 'Ditolak'],
            self::Assessment => ['Siswa', 'NIS', 'Perusahaan', 'Nilai Industri', 'Nilai Guru', 'Nilai Akhir', 'Grade', 'Status'],
            self::Monitoring => ['Siswa', 'NIS', 'Perusahaan', 'Guru', 'Jumlah Kunjungan', 'Kunjungan Terakhir'],
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
