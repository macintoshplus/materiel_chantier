<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Distance à vol d'oiseau (haversine) entre deux coordonnées GPS, en kilomètres.
 */
final class DistanceCalculator
{
    private const float RAYON_TERRE_KM = 6371.0;

    public function distanceKm(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $phiA = deg2rad($latitudeA);
        $phiB = deg2rad($latitudeB);
        $dPhi = deg2rad($latitudeB - $latitudeA);
        $dLambda = deg2rad($longitudeB - $longitudeA);

        $a = sin($dPhi / 2) ** 2 + cos($phiA) * cos($phiB) * sin($dLambda / 2) ** 2;

        return 2 * self::RAYON_TERRE_KM * asin(min(1.0, sqrt($a)));
    }
}
