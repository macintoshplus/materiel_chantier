<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\DistanceCalculator;
use PHPUnit\Framework\TestCase;

final class DistanceCalculatorTest extends TestCase
{
    public function testUnDegreDeLatitudeVaut111Km(): void
    {
        self::assertEqualsWithDelta(111.195, (new DistanceCalculator())->distanceKm(0, 0, 1, 0), 0.01);
    }

    public function testDemiTourDeLEquateur(): void
    {
        self::assertEqualsWithDelta(20015.09, (new DistanceCalculator())->distanceKm(0, 0, 0, 180), 0.1);
    }

    public function testDeuxPointsIdentiquesSontADistanceNulle(): void
    {
        self::assertEqualsWithDelta(0.0, (new DistanceCalculator())->distanceKm(48.85, 2.35, 48.85, 2.35), 1e-9);
    }
}
