<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Models\Concerns\HasAttachments;
use Carbon\CarbonImmutable;
use Database\Factories\JournalFactory;
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
 * @property string $id
 * @property string $placement_id
 * @property CarbonImmutable $date
 * @property string $activity
 * @property string $work_result
 * @property string|null $obstacle
 * @property ReviewStatus $status
 * @property ReviewStatus $industry_status
 * @property string|null $industry_reviewed_by
 * @property CarbonImmutable|null $industry_reviewed_at
 * @property string|null $industry_note
 * @property ReviewStatus $teacher_status
 * @property string|null $teacher_reviewed_by
 * @property CarbonImmutable|null $teacher_reviewed_at
 * @property string|null $teacher_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Placement $placement
 * @property-read User|null $industryReviewer
 * @property-read User|null $teacherReviewer
 * @property-read Collection<int, Attachment> $attachments
 */
#[Fillable([
    'placement_id', 'date', 'activity', 'work_result', 'obstacle', 'status',
    'industry_status', 'industry_reviewed_by', 'industry_reviewed_at', 'industry_note',
    'teacher_status', 'teacher_reviewed_by', 'teacher_reviewed_at', 'teacher_note',
])]
class Journal extends Model
{
    use HasAttachments;

    /** @use HasFactory<JournalFactory> */
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
            'date' => 'immutable_date',
            'status' => ReviewStatus::class,
            'industry_status' => ReviewStatus::class,
            'teacher_status' => ReviewStatus::class,
            'industry_reviewed_at' => 'immutable_datetime',
            'teacher_reviewed_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('journal')
            ->logOnly([
                'activity', 'work_result', 'obstacle', 'status',
                'industry_status', 'industry_reviewed_by', 'industry_note',
                'teacher_status', 'teacher_reviewed_by', 'teacher_note',
            ])
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
    public function industryReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'industry_reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacherReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_reviewed_by');
    }

    /**
     * Whether the student may still change the content.
     *
     * Locked exactly while someone's approval stands against the current text:
     * once industry approves, the journal is in the teacher's hands, and once both
     * approve it is a record. A rejection at either stage reopens it — otherwise a
     * rejected journal would be a dead end, since UNIQUE(placement_id, date) stops
     * the student writing that day again.
     */
    public function isEditable(): bool
    {
        if ($this->teacher_status === ReviewStatus::Rejected) {
            return true;
        }

        if ($this->industry_status === ReviewStatus::Rejected) {
            return true;
        }

        return $this->industry_status === ReviewStatus::Pending;
    }

    public function awaitingIndustry(): bool
    {
        return $this->industry_status === ReviewStatus::Pending;
    }

    /**
     * The teacher's turn comes only after industry approval (PRD § 5).
     */
    public function awaitingTeacher(): bool
    {
        return $this->industry_status === ReviewStatus::Approved
            && $this->teacher_status === ReviewStatus::Pending;
    }

    /**
     * @param  Builder<Journal>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        // Journals hang off placements, so visibility is decided in exactly one
        // place: Placement::scopeVisibleTo (ADD § 3.2).
        $query->whereIn(
            'placement_id',
            Placement::query()->visibleTo($user)->select('id'),
        );
    }

    /**
     * @param  Builder<Journal>  $query
     */
    public function scopePendingForIndustry(Builder $query): void
    {
        $query->where('industry_status', ReviewStatus::Pending);
    }

    /**
     * @param  Builder<Journal>  $query
     */
    public function scopePendingForTeacher(Builder $query): void
    {
        $query->where('industry_status', ReviewStatus::Approved)
            ->where('teacher_status', ReviewStatus::Pending);
    }
}
