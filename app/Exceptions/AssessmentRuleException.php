<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\AssessmentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AssessmentRuleException extends RuntimeException
{
    public static function incomplete(): self
    {
        return new self(
            'Nilai akhir belum dapat dihitung: penilaian industri dan penilaian guru harus dikirim terlebih dahulu.'
        );
    }

    public static function alreadySubmitted(AssessmentType $type): self
    {
        return new self(sprintf(
            '%s sudah dikirim dan tidak dapat diubah lagi. Hubungi Admin PKL untuk membukanya.',
            $type->label(),
        ));
    }

    public static function placementNotAssessable(): self
    {
        return new self(
            'Penilaian hanya dapat diisi untuk PKL yang aktif atau sudah selesai.'
        );
    }

    public static function alreadyPublished(): self
    {
        return new self(
            'Nilai sudah dipublikasikan. Batalkan publikasi terlebih dahulu sebelum menghitung ulang.'
        );
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->with('error', $this->getMessage());
    }
}
