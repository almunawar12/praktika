<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Carbon\CarbonImmutable;
use Database\Factories\MonitoringFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A teacher's visit report (PRD § 4, Modul Monitoring Guru).
 *
 * @property string $id
 * @property string $placement_id
 * @property string $teacher_id
 * @property CarbonImmutable $visit_date
 * @property string $note
 * @property string|null $recommendation
 * @property string|null $latitude
 * @property string|null $longitude
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Placement $placement
 * @property-read Teacher $teacher
 * @property-read Collection<int, Attachment> $attachments
 */
#[Fillable(['placement_id', 'teacher_id', 'visit_date', 'note', 'recommendation', 'latitude', 'longitude'])]
class Monitoring extends Model
{
    use HasAttachments;

    /** @use HasFactory<MonitoringFactory> */
    use HasFactory;

    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['visit_date' => 'immutable_date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('monitoring')
            ->logOnly(['placement_id', 'teacher_id', 'visit_date', 'note', 'recommendation'])
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
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * @param  Builder<Monitoring>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        // Monitoring hangs off placements, so visibility is decided in exactly one
        // place: Placement::scopeVisibleTo (ADD § 3.2).
        $query->whereIn(
            'placement_id',
            Placement::query()->visibleTo($user)->select('id'),
        );
    }
}
