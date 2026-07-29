<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PlacementStatus;
use App\Models\Placement;
use App\Services\PlacementService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Moves placements whose `end_date` has passed from `active` to `completed`.
 *
 * Without this, a PKL that simply runs out its term stays `active` forever —
 * final scoring and reporting both key off `completed`.
 */
class CompleteExpiredPlacementsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $forDate = null) {}

    public function handle(PlacementService $placements): void
    {
        $date = $this->forDate === null
            ? CarbonImmutable::now()->startOfDay()
            : CarbonImmutable::parse($this->forDate)->startOfDay();

        Placement::query()
            ->where('status', PlacementStatus::Active)
            ->whereDate('end_date', '<', $date)
            ->chunkById(200, function ($chunk) use ($placements): void {
                foreach ($chunk as $placement) {
                    $placements->complete($placement);
                }
            });
    }
}
