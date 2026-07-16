<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PlacementStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MonitoringRuleException extends RuntimeException
{
    public static function placementNotVisitable(PlacementStatus $status): self
    {
        return new self(sprintf(
            'Kunjungan hanya dapat dicatat untuk PKL yang aktif atau sudah selesai. Penempatan ini berstatus %s.',
            $status->label(),
        ));
    }

    public static function outsidePlacementPeriod(): self
    {
        return new self('Tanggal kunjungan berada di luar periode PKL.');
    }

    public static function futureDate(): self
    {
        return new self('Tanggal kunjungan tidak boleh di masa depan.');
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->with('error', $this->getMessage());
    }
}
