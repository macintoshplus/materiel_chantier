<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Tolérance géométrique unique (mm) et arrondis entiers : aucun flottant n'est persisté.
 */
final class Tolerance
{
    public const MM = 1;

    public static function egal(int $a, int $b): bool
    {
        return abs($a - $b) <= self::MM;
    }

    public static function arrondir(float $valeur): int
    {
        return (int) round($valeur);
    }
}
