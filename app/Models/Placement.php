<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlacementStatus;
use App\Enums\RoleName;
use Carbon\CarbonImmutable;
use Database\Factories\PlacementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property string $student_id
 * @property string $company_id
 * @property string $teacher_id
 * @property PlacementStatus $status
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property string|null $cancel_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Student $student
 * @property-read Company $company
 * @property-read Teacher $teacher
 * @property-read Collection<int, Attendance> $attendances
 */
#[Fillable(['student_id', 'company_id', 'teacher_id', 'status', 'start_date', 'end_date', 'cancel_reason'])]
class Placement extends Model
{
    /** @use HasFactory<PlacementFactory> */
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
            'status' => PlacementStatus::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('placement')
            ->logOnly(['student_id', 'company_id', 'teacher_id', 'status', 'start_date', 'end_date', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @param  Builder<Placement>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', PlacementStatus::Active);
    }

    /**
     * Row-level scope for the whole PKL graph (ADD § 3.2).
     *
     * Every transaction module to come — attendance, journals, monitoring,
     * assessments — hangs off placements, so this is the one place that decides who
     * can see whose PKL. Implementing it per-module would guarantee drift.
     *
     * @param  Builder<Placement>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        // Admins see everything.
        if ($user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::AdminPkl->value])) {
            return;
        }

        if ($user->hasRole(RoleName::GuruPembimbing->value)) {
            $query->where('teacher_id', $user->teacher?->id);

            return;
        }

        if ($user->hasRole(RoleName::Siswa->value)) {
            $query->where('student_id', $user->student?->id);

            return;
        }

        // Pembimbing Industri is scoped by company, but the company_supervisors module
        // does not exist yet, so there is no link to scope by. Deny rather than leak:
        // returning unscoped here would show one company's supervisor every school's
        // placements.
        $query->whereRaw('1 = 0');
    }
}
