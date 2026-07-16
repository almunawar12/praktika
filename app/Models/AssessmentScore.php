<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssessmentCriteria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One criterion's score, 0–100 (PRD § 4).
 *
 * @property string $id
 * @property string $assessment_id
 * @property AssessmentCriteria $criteria_code
 * @property string $score
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Assessment $assessment
 */
#[Fillable(['assessment_id', 'criteria_code', 'score'])]
class AssessmentScore extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['criteria_code' => AssessmentCriteria::class];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }
}
