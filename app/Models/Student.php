<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlacementStatus;
use App\Enums\RoleName;
use Carbon\CarbonImmutable;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Row-level scope (ADD § 9, StudentPolicy).
     *
     * The student directory carries names, NIS, NISN, email and phone numbers of
     * minors. School staff see the school; a Pembimbing Industri is an external
     * party and sees only the students actually placed with their company.
     *
     * @param  Builder<Student>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        // School staff see the whole directory.
        if ($user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::AdminPkl->value,
            RoleName::GuruPembimbing->value,
        ])) {
            return;
        }

        if ($user->hasRole(RoleName::PembimbingIndustri->value)) {
            $query->whereIn(
                'id',
                Placement::query()
                    ->where('company_id', $user->companySupervisor?->company_id)
                    ->select('student_id'),
            );

            return;
        }

        if ($user->hasRole(RoleName::Siswa->value)) {
            $query->whereKey($user->student?->id);

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
