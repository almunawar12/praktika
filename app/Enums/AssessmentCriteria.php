<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The nine criteria from PRD § 4, each scored 0–100.
 *
 * One enum rather than two: the criteria are stored by code in a single
 * `assessment_scores` table, so a single source keeps the codes unique and lets a
 * score always resolve back to which side it belongs to.
 */
enum AssessmentCriteria: string
{
    // Penilaian Industri.
    case Disiplin = 'disiplin';
    case TanggungJawab = 'tanggung_jawab';
    case Kerjasama = 'kerjasama';
    case Komunikasi = 'komunikasi';
    case KompetensiTeknis = 'kompetensi_teknis';

    // Penilaian Guru.
    case Kehadiran = 'kehadiran';
    case KelengkapanJurnal = 'kelengkapan_jurnal';
    case Sikap = 'sikap';
    case LaporanAkhir = 'laporan_akhir';

    public function label(): string
    {
        return match ($this) {
            self::Disiplin => 'Disiplin',
            self::TanggungJawab => 'Tanggung Jawab',
            self::Kerjasama => 'Kerjasama',
            self::Komunikasi => 'Komunikasi',
            self::KompetensiTeknis => 'Kompetensi Teknis',
            self::Kehadiran => 'Kehadiran',
            self::KelengkapanJurnal => 'Kelengkapan Jurnal',
            self::Sikap => 'Sikap',
            self::LaporanAkhir => 'Laporan Akhir',
        };
    }

    public function type(): AssessmentType
    {
        return match ($this) {
            self::Disiplin,
            self::TanggungJawab,
            self::Kerjasama,
            self::Komunikasi,
            self::KompetensiTeknis => AssessmentType::Industry,

            self::Kehadiran,
            self::KelengkapanJurnal,
            self::Sikap,
            self::LaporanAkhir => AssessmentType::Teacher,
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
