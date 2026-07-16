<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class JournalRuleException extends RuntimeException
{
    public static function noActivePlacement(): self
    {
        return new self('Anda belum memiliki PKL aktif, sehingga belum dapat mengisi jurnal.');
    }

    public static function outsidePlacementPeriod(): self
    {
        return new self('Tanggal tersebut berada di luar periode PKL Anda.');
    }

    public static function alreadyExists(): self
    {
        return new self('Jurnal untuk tanggal tersebut sudah ada. Buka jurnal itu untuk mengubahnya.');
    }

    public static function locked(): self
    {
        return new self('Jurnal yang sudah disetujui pembimbing industri tidak dapat diubah lagi.');
    }

    public static function industryReviewPending(): self
    {
        return new self('Jurnal ini belum disetujui pembimbing industri, sehingga belum dapat direview guru.');
    }

    public static function futureDate(): self
    {
        return new self('Jurnal tidak dapat diisi untuk tanggal yang belum terjadi.');
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->with('error', $this->getMessage());
    }
}
