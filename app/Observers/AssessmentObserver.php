<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Assessment;
use App\Services\ScoreCalculatorService;

/**
 * Keeps the final score in step with the assessments it is derived from.
 *
 * Without this, an admin would have to remember to press Calculate for every
 * student — and a forgotten press means a score that never appears, with nothing
 * to signal it.
 */
class AssessmentObserver
{
    public function __construct(private readonly ScoreCalculatorService $calculator) {}

    public function saved(Assessment $assessment): void
    {
        // Quiet by design: most saves are drafts, and a draft simply is not enough to
        // compute anything yet. recalculateIfReady() returns null rather than throwing
        // so an ordinary save is never an error.
        $this->calculator->recalculateIfReady($assessment->placement);
    }
}
