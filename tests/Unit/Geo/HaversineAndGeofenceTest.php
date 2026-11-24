<?php

declare(strict_types=1);

use App\Models\OfficeLocation;
use App\Support\Geo\GeofenceEvaluator;
use App\Support\Geo\HaversineCalculator;

test('HaversineCalculator mengembalikan 0 meter pada koordinat yang identik', function (): void {
    $calculator = new HaversineCalculator;

    $distance = $calculator->distanceMeters(-6.3105800, 107.1436200, -6.3105800, 107.1436200);

    expect($distance)->toBe(0.0);
});

test('HaversineCalculator menghitung jarak sekitar 100 meter dan 1 kilometer secara akurat', function (): void {
    $calculator = new HaversineCalculator;
    $baseLat = -6.3105800;
    $baseLng = 107.1436200;

    // 1 derajat lintang pada bumi radius 6.371.000 m ~ 111.194,93 m
    $deltaLat100m = 100.0 / (deg2rad(1) * HaversineCalculator::EARTH_RADIUS_METERS);
    $deltaLat1km = 1000.0 / (deg2rad(1) * HaversineCalculator::EARTH_RADIUS_METERS);

    $distance100m = $calculator->distanceMeters($baseLat, $baseLng, $baseLat + $deltaLat100m, $baseLng);
    $distance1km = $calculator->distanceMeters($baseLat, $baseLng, $baseLat + $deltaLat1km, $baseLng);

    expect(abs($distance100m - 100.0))->toBeLessThan(0.05)
        ->and(abs($distance1km - 1000.0))->toBeLessThan(0.05);
});

test('GeofenceEvaluator mengembalikan hasActiveOffices false bila tidak ada kantor aktif', function (): void {
    $evaluator = new GeofenceEvaluator(new HaversineCalculator);

    $emptyResult = $evaluator->evaluate(-6.3105800, 107.1436200, collect());

    expect($emptyResult->isInside)->toBeFalse()
        ->and($emptyResult->nearestOffice)->toBeNull()
        ->and($emptyResult->distanceMeters)->toBeNull()
        ->and($emptyResult->hasActiveOffices)->toBeFalse();

    $onlyInactive = collect([
        new OfficeLocation([
            'name' => 'Kantor Nonaktif',
            'lat' => -6.3105800,
            'lng' => 107.1436200,
            'radius_meters' => 150,
            'is_active' => false,
        ]),
    ]);

    $inactiveResult = $evaluator->evaluate(-6.3105800, 107.1436200, $onlyInactive);

    expect($inactiveResult->isInside)->toBeFalse()
        ->and($inactiveResult->nearestOffice)->toBeNull()
        ->and($inactiveResult->distanceMeters)->toBeNull()
        ->and($inactiveResult->hasActiveOffices)->toBeFalse();
});

test('GeofenceEvaluator mengenali koordinat di dalam batas, tepat pada batas, dan di luar radius kantor', function (): void {
    $office = new OfficeLocation([
        'name' => 'Veloce HQ Cikarang',
        'lat' => -6.3105800,
        'lng' => 107.1436200,
        'radius_meters' => 150,
        'is_active' => true,
    ]);

    $realEvaluator = new GeofenceEvaluator(new HaversineCalculator);

    // Di dalam radius (~100 m dari kantor beradius 150 m)
    $deltaLat100m = 100.0 / (deg2rad(1) * HaversineCalculator::EARTH_RADIUS_METERS);
    $insideResult = $realEvaluator->evaluate(-6.3105800 + $deltaLat100m, 107.1436200, collect([$office]));

    expect($insideResult->isInside)->toBeTrue()
        ->and($insideResult->hasActiveOffices)->toBeTrue()
        ->and($insideResult->nearestOffice)->toBe($office)
        ->and($insideResult->distanceMeters)->toBe(100.0);

    // Di luar radius (~300 m dari kantor beradius 150 m)
    $deltaLat300m = 300.0 / (deg2rad(1) * HaversineCalculator::EARTH_RADIUS_METERS);
    $outsideResult = $realEvaluator->evaluate(-6.3105800 + $deltaLat300m, 107.1436200, collect([$office]));

    expect($outsideResult->isInside)->toBeFalse()
        ->and($outsideResult->hasActiveOffices)->toBeTrue()
        ->and($outsideResult->nearestOffice)->toBe($office)
        ->and($outsideResult->distanceMeters)->toBe(300.0);

    // Tepat pada batas (distance === radius_meters) menggunakan calculator deterministik
    $exactCalculator = new class extends HaversineCalculator
    {
        public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
        {
            return 150.0;
        }
    };
    $exactEvaluator = new GeofenceEvaluator($exactCalculator);
    $exactResult = $exactEvaluator->evaluate(-6.3105800, 107.1436200, collect([$office]));

    expect($exactResult->isInside)->toBeTrue()
        ->and($exactResult->distanceMeters)->toBe(150.0);
});

test('GeofenceEvaluator memilih kantor aktif terdekat dan mengabaikan kantor nonaktif', function (): void {
    $calculator = new HaversineCalculator;
    $evaluator = new GeofenceEvaluator($calculator);

    $baseLat = -6.3105800;
    $baseLng = 107.1436200;
    $metersPerDegLat = deg2rad(1) * HaversineCalculator::EARTH_RADIUS_METERS;

    // Kantor nonaktif tepat di koordinat pengguna (0 m) harus diabaikan
    $inactiveOffice = new OfficeLocation([
        'name' => 'Gudang Lama Nonaktif',
        'lat' => $baseLat,
        'lng' => $baseLng,
        'radius_meters' => 100,
        'is_active' => false,
    ]);

    // Kantor aktif kedua berjarak ~250 m (radius 100 m)
    $farOffice = new OfficeLocation([
        'name' => 'Kantor Cabang Bekasi',
        'lat' => $baseLat + (250.0 / $metersPerDegLat),
        'lng' => $baseLng,
        'radius_meters' => 100,
        'is_active' => true,
    ]);

    // Kantor aktif pertama berjarak ~60 m (radius 150 m)
    $nearOffice = new OfficeLocation([
        'name' => 'Kantor Utama Cikarang',
        'lat' => $baseLat + (60.0 / $metersPerDegLat),
        'lng' => $baseLng,
        'radius_meters' => 150,
        'is_active' => true,
    ]);

    $result = $evaluator->evaluate(
        $baseLat,
        $baseLng,
        collect([$inactiveOffice, $farOffice, $nearOffice]),
    );

    expect($result->isInside)->toBeTrue()
        ->and($result->hasActiveOffices)->toBeTrue()
        ->and($result->nearestOffice)->toBe($nearOffice)
        ->and($result->distanceMeters)->toBe(60.0);
});
