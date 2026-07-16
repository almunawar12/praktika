<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\VerificationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AttendanceFactory;
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
 * @property CarbonImmutable $date
 * @property AttendanceStatus $status
 * @property CarbonImmutable|null $check_in_at
 * @property string|null $check_in_latitude
 * @property string|null $check_in_longitude
 * @property string|null $check_in_photo_path
 * @property int|null $check_in_distance_m
 * @property CarbonImmutable|null $check_out_at
 * @property string|null $check_out_latitude
 * @property string|null $check_out_longitude
 * @property string|null $check_out_photo_path
 * @property int|null $check_out_distance_m
 * @property string|null $notes
 * @property VerificationStatus $verification_status
 * @property string|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property string|null $verification_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Placement $placement
 * @property-read User|null $verifier
 */
#[Fillable([
    'placement_id', 'date', 'status', 'notes',
    'check_in_at', 'check_in_latitude', 'check_in_longitude', 'check_in_photo_path', 'check_in_distance_m',
    'check_out_at', 'check_out_latitude', 'check_out_longitude', 'check_out_photo_path', 'check_out_distance_m',
    'verification_status', 'verified_by', 'verified_at', 'verification_note',
])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    use HasUuids;
    use LogsActivity;

    /**
     * Deliberately no SoftDeletes: attendance is the evidence base for the teacher's
     * assessment. Corrections go through the verification flow, not deletion.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'status' => AttendanceStatus::class,
            'verification_status' => VerificationStatus::class,
            'check_in_at' => 'immutable_datetime',
            'check_out_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'check_in_distance_m' => 'integer',
            'check_out_distance_m' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('attendance')
            ->logOnly(['status', 'check_in_at', 'check_out_at', 'verification_status', 'verified_by', 'notes'])
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
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function hasCheckedOut(): bool
    {
        return $this->check_out_at !== null;
    }

    /**
     * Whether the recorded position was outside the company's geofence.
     *
     * Null when the company has no coordinates configured — "unknown" is not the
     * same as "inside", and the UI must be able to tell them apart.
     */
    public function checkInOutsideGeofence(): ?bool
    {
        $radius = $this->placement->company->geofence_radius_m;

        if ($radius === null || $this->check_in_distance_m === null) {
            return null;
        }

        return $this->check_in_distance_m > $radius;
    }

    /**
     * @param  Builder<Attendance>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        // Attendance hangs off placements, so visibility is decided in exactly one
        // place: Placement::scopeVisibleTo (ADD § 3.2). Expressed as a subquery
        // rather than whereHas() because that hands the closure an untyped builder,
        // where the scope would not resolve.
        $query->whereIn(
            'placement_id',
            Placement::query()->visibleTo($user)->select('id'),
        );
    }

    /**
     * @param  Builder<Attendance>  $query
     */
    public function scopeAwaitingVerification(Builder $query): void
    {
        $query->where('verification_status', VerificationStatus::Pending);
    }
}
