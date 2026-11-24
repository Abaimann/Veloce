<?php

declare(strict_types=1);

namespace App\Support\Geo;

class HaversineCalculator
{
    public const float EARTH_RADIUS_METERS = 6371000.0;

    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $sinHalfLat = sin($latDelta / 2);
        $sinHalfLng = sin($lngDelta / 2);

        $a = ($sinHalfLat * $sinHalfLat)
            + cos($latFrom) * cos($latTo) * ($sinHalfLng * $sinHalfLng);

        // Clamp ke [0, 1] untuk mencegah NaN akibat pembulatan floating-point pada titik antipodal.
        $a = min(1.0, max(0.0, $a));

        $c = 2 * atan2(sqrt($a), sqrt(1.0 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
