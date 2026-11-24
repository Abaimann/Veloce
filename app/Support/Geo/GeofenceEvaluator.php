<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Models\OfficeLocation;
use Illuminate\Support\Collection;

class GeofenceEvaluator
{
    public function __construct(
        private readonly HaversineCalculator $calculator,
    ) {}

    /**
     * Mengevaluasi posisi koordinat terhadap seluruh lokasi kantor aktif.
     *
     * @param  Collection<int, OfficeLocation>|null  $locations
     */
    public function evaluate(float $lat, float $lng, ?Collection $locations = null): GeofenceResult
    {
        $activeLocations = ($locations ?? OfficeLocation::query()->active()->get())
            ->filter(static fn (OfficeLocation $office): bool => $office->is_active !== false)
            ->values();

        if ($activeLocations->isEmpty()) {
            return new GeofenceResult(false, null, null, false);
        }

        $nearestOffice = null;
        $nearestDistance = null;
        $isInside = false;

        foreach ($activeLocations as $office) {
            $distance = $this->calculator->distanceMeters(
                $lat,
                $lng,
                (float) $office->lat,
                (float) $office->lng,
            );

            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestOffice = $office;
            }

            if ($distance <= (float) $office->radius_meters) {
                $isInside = true;
            }
        }

        return new GeofenceResult(
            isInside: $isInside,
            nearestOffice: $nearestOffice,
            distanceMeters: $nearestDistance !== null ? round($nearestDistance, 2) : null,
            hasActiveOffices: true,
        );
    }
}
