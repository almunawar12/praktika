<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PlacementStatus;
use App\Models\Company;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A placement rule an admin can actually do something about.
 *
 * Rendered as a flash message rather than a 500: "kuota penuh" and "siswa sudah
 * punya PKL aktif" are ordinary outcomes of a busy placement season, not crashes.
 */
class PlacementRuleException extends RuntimeException
{
    public static function quotaExceeded(Company $company, int $used): self
    {
        return new self(sprintf(
            'Kuota %s sudah penuh (%d dari %d terisi). Naikkan kuota perusahaan atau pilih perusahaan lain.',
            $company->name,
            $used,
            $company->quota,
        ));
    }

    public static function studentAlreadyActive(Student $student): self
    {
        return new self(sprintf(
            'Siswa %s sudah memiliki PKL aktif. Selesaikan atau batalkan penempatan tersebut lebih dulu.',
            $student->name,
        ));
    }

    public static function invalidTransition(PlacementStatus $from, PlacementStatus $to): self
    {
        return new self(sprintf(
            'Penempatan berstatus %s tidak dapat diubah menjadi %s.',
            $from->label(),
            $to->label(),
        ));
    }

    public static function companyInactive(Company $company): self
    {
        return new self(sprintf(
            '%s berstatus nonaktif dan tidak dapat menerima siswa PKL.',
            $company->name,
        ));
    }

    public static function notEditable(PlacementStatus $status): self
    {
        return new self(sprintf(
            'Penempatan berstatus %s tidak dapat diubah lagi.',
            $status->label(),
        ));
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->with('error', $this->getMessage());
    }
}
