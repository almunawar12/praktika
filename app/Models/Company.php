<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlacementStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
