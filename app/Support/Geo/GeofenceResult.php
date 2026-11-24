<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Models\OfficeLocation;

final readonly class GeofenceResult
{
    public function __construct(
        public bool $isInside,
        public ?OfficeLocation $nearestOffice,
        public ?float $distanceMeters,
        public bool $hasActiveOffices,
    ) {}
}
