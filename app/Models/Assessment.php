<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssessmentType;
use App\Observers\AssessmentObserver;
use Carbon\CarbonImmutable;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $placement_id
 * @property AssessmentType $type
 * @property string $assessor_id
 * @property string|null $average_score
 * @property string|null $note
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Placement $placement
 * @property-read User $assessor
 * @property-read Collection<int, AssessmentScore> $scores
 */
#[Fillable(['placement_id', 'type', 'assessor_id', 'average_score', 'note', 'submitted_at'])]
#[ObservedBy(AssessmentObserver::class)]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssessmentType::class,
            'submitted_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assessment')
            ->logOnly(['type', 'assessor_id', 'average_score', 'note', 'submitted_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Placement, $this>
     */
    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    /**
     * @return HasMany<AssessmentScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    /**
     * A draft is still the assessor's to change; a submitted one is evidence the
     * final score rests on.
     */
    public function isDraft(): bool
    {
        return $this->submitted_at === null;
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * @param  Builder<Assessment>  $query
     */
    public function scopeSubmitted(Builder $query): void
    {
        $query->whereNotNull('submitted_at');
    }

    /**
     * @param  Builder<Assessment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        // Assessments hang off placements, so visibility is decided in exactly one
        // place: Placement::scopeVisibleTo (ADD § 3.2).
        $query->whereIn(
            'placement_id',
            Placement::query()->visibleTo($user)->select('id'),
        );
    }
}
