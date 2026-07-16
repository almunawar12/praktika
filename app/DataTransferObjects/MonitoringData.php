<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\Monitoring\StoreMonitoringRequest;
use App\Http\Requests\Monitoring\UpdateMonitoringRequest;

final readonly class MonitoringData
{
    public function __construct(
        public string $visitDate,
        public string $note,
        public ?string $recommendation,
        public ?float $latitude,
        public ?float $longitude,
    ) {}

    public static function fromRequest(StoreMonitoringRequest|UpdateMonitoringRequest $request): self
    {
        return new self(
            visitDate: (string) $request->string('visit_date'),
            note: (string) $request->string('note'),
            recommendation: $request->filled('recommendation')
                ? (string) $request->string('recommendation')
                : null,
            latitude: $request->filled('latitude') ? (float) $request->input('latitude') : null,
            longitude: $request->filled('longitude') ? (float) $request->input('longitude') : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'visit_date' => $this->visitDate,
            'note' => $this->note,
            'recommendation' => $this->recommendation,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
