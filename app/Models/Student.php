<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlacementStatus;
use Carbon\CarbonImmutable;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $user_id
 * @property string $class_id
 * @property string $nis
 * @property string $nisn
 * @property string $name
 * @property string|null $phone
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read User $user
 * @property-read SchoolClass $schoolClass
 * @property-read Collection<int, Placement> $placements
 * @property-read Placement|null $activePlacement
 */
#[Fillable(['user_id', 'class_id', 'nis', 'nisn', 'name', 'phone'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('student')
            ->logOnly(['nis', 'nisn', 'name', 'phone', 'class_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return HasMany<Placement, $this>
     */
    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    /**
     * The one placement a student may have in flight.
     *
     * `hasOne` rather than `hasMany` because the partial unique index
     * `placements_one_active_per_student` makes more than one impossible.
     *
     * @return HasOne<Placement, $this>
     */
    public function activePlacement(): HasOne
    {
        return $this->hasOne(Placement::class)->where('status', PlacementStatus::Active);
    }

    /**
     * The student's login email. Lives on `users`, which is the single source of
     * truth for credentials.
     */
    public function email(): string
    {
        return $this->user->email;
    }
}
