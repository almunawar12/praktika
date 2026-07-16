<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * An attendance rule the student can understand and act on. Rendered as a flash
 * message: "already checked in" and "outside the area" are everyday outcomes.
 */
class AttendanceRuleException extends RuntimeException
{
    public static function noActivePlacement(): self
    {
        return new self('Anda belum memiliki PKL aktif, sehingga belum dapat melakukan absensi.');
    }

    public static function outsidePlacementPeriod(): self
    {
        return new self('Tanggal hari ini berada di luar periode PKL Anda.');
    }

    public static function alreadyCheckedIn(): self
    {
        return new self('Anda sudah melakukan absensi hari ini.');
    }

    public static function notCheckedIn(): self
    {
        return new self('Anda belum melakukan check in hari ini.');
    }

    public static function alreadyCheckedOut(): self
    {
        return new self('Anda sudah melakukan check out hari ini.');
    }

    public static function outsideGeofence(Company $company, int $distance, int $radius): self
    {
        return new self(sprintf(
            'Anda berada %s dari %s, di luar radius %d meter yang diizinkan. Lakukan absensi dari lokasi PKL.',
            $distance >= 1000
                ? number_format($distance / 1000, 1, ',', '.').' km'
                : $distance.' meter',
            $company->name,
            $radius,
        ));
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->with('error', $this->getMessage());
    }
}
