<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\Attendance\CheckInRequest;
use App\Http\Requests\Attendance\CheckOutRequest;
use Illuminate\Http\UploadedFile;

final readonly class CheckInData
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public UploadedFile $photo,
    ) {}

    public static function fromRequest(CheckInRequest|CheckOutRequest $request): self
    {
        /** @var UploadedFile $photo */
        $photo = $request->file('photo');

        return new self(
            latitude: (float) $request->input('latitude'),
            longitude: (float) $request->input('longitude'),
            photo: $photo,
        );
    }
}
