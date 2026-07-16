<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;

class GeolocationService
{
    /**
     * Mean Earth radius in metres.
     */
    private const EARTH_RADIUS_M = 6_371_000;

    /**
     * Great-circle distance between two points, in metres.
     *
     * Haversine rather than a flat-earth approximation: at Indonesian latitudes the
     * error is small either way, but haversine costs nothing extra and does not
     * quietly degrade near the poles or across the antimeridian.
     */
    public function distanceMeters(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude,
    ): int {
        $latFrom = deg2rad($fromLatitude);
        $latTo = deg2rad($toLatitude);
        $latDelta = $latTo - $latFrom;
        $lonDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        return (int) round(2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a))));
    }

    /**
     * Distance from a point to a company, or null when the company has no
     * coordinates configured.
     */
    public function distanceToCompany(Company $company, float $latitude, float $longitude): ?int
    {
        if ($company->latitude === null || $company->longitude === null) {
            return null;
        }

        return $this->distanceMeters(
            $latitude,
            $longitude,
            (float) $company->latitude,
            (float) $company->longitude,
        );
    }

    /**
     * Whether a company can have its geofence enforced at all.
     *
     * A company with no coordinates or no radius has nothing to compare against, so
     * check-in there is accepted and the distance recorded as unknown. Blocking
     * instead would stop students from working because an admin left a field empty.
     */
    public function hasGeofence(Company $company): bool
    {
        return $company->latitude !== null
            && $company->longitude !== null
            && $company->geofence_radius_m !== null;
    }
}
