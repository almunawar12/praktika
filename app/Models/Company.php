<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlacementStatus;
use App\Enums\RoleName;
use Carbon\CarbonImmutable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $name
 * @property string $business_field
 * @property string $address
 * @property string $pic_name
 * @property string $pic_phone
 * @property int $quota
 * @property bool $is_active
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $geofence_radius_m
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Placement> $placements
 * @property-read Collection<int, Placement> $activePlacements
 * @property-read Collection<int, CompanySupervisor> $supervisors
 */
#[Fillable([
    'name', 'business_field', 'address', 'pic_name', 'pic_phone', 'quota', 'is_active',
    'latitude', 'longitude', 'geofence_radius_m',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
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
            'quota' => 'integer',
            'is_active' => 'boolean',
            'geofence_radius_m' => 'integer',
        ];
    }

    /**
     * @return HasMany<CompanySupervisor, $this>
     */
    public function supervisors(): HasMany
    {
        return $this->hasMany(CompanySupervisor::class);
    }

    /**
     * Row-level scope (ADD § 9, CompanyPolicy).
     *
     * The DUDI list carries every company's quota and PIC phone number — commercial
     * detail that a student or a rival company's supervisor has no business
     * browsing. School staff see all of it; everyone else sees only the company
     * they are attached to.
     *
     * @param  Builder<Company>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::AdminPkl->value,
            RoleName::GuruPembimbing->value,
        ])) {
            return;
        }

        if ($user->hasRole(RoleName::PembimbingIndustri->value)) {
            $query->whereKey($user->companySupervisor?->company_id);

            return;
        }

        if ($user->hasRole(RoleName::Siswa->value)) {
            $query->whereIn(
                'id',
                Placement::query()
                    ->where('student_id', $user->student?->id)
                    ->select('company_id'),
            );

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * @return HasMany<Placement, $this>
     */
    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    /**
     * Placements currently consuming a quota slot.
     *
     * @return HasMany<Placement, $this>
     */
    public function activePlacements(): HasMany
    {
        return $this->hasMany(Placement::class)->where('status', PlacementStatus::Active);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('company')
            ->logOnly([
                'name', 'business_field', 'address', 'pic_name', 'pic_phone', 'quota', 'is_active',
                'latitude', 'longitude', 'geofence_radius_m',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
