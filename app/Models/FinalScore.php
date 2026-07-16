<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use Carbon\CarbonImmutable;
use Database\Factories\FinalScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $placement_id
 * @property string $industry_score
 * @property string $teacher_score
 * @property string $industry_weight
 * @property string $teacher_weight
 * @property string $final_score
 * @property string $grade
 * @property CarbonImmutable|null $published_at
 * @property string|null $published_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Placement $placement
 * @property-read User|null $publisher
 */
#[Fillable([
    'placement_id', 'industry_score', 'teacher_score',
    'industry_weight', 'teacher_weight', 'final_score', 'grade',
    'published_at', 'published_by',
])]
class FinalScore extends Model
{
    /** @use HasFactory<FinalScoreFactory> */
    use HasFactory;

    use HasUuids;
    use LogsActivity;

    /**
     * Deliberately no SoftDeletes: a final score is the outcome of the whole PKL.
     * It is unpublished, never deleted.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['published_at' => 'immutable_datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('final-score')
            ->logOnly(['industry_score', 'teacher_score', 'final_score', 'grade', 'published_at', 'published_by'])
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
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * @param  Builder<FinalScore>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * @param  Builder<FinalScore>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn(
            'placement_id',
            Placement::query()->visibleTo($user)->select('id'),
        );

        // A student only ever sees a result the school has decided to release
        // (ADD § 9). Everyone else may see it while it is still being worked on.
        if ($user->hasRole(RoleName::Siswa->value)) {
            $query->published();
        }
    }
}
