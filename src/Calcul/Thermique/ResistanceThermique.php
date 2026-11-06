<?php

declare(strict_types=1);

namespace App\Calcul\Thermique;

use App\Enum\TypeSurface;

/**
 * Bilan thermique d'une paroi, en entiers : résistances en millièmes de m².K/W, coefficient U en millièmes de
 * W/m².K. RT = Rsi + R(paroi existante) + somme des R du système + Rse.
 */
final class ResistanceThermique
{
    /** @return int Rsi (millièmes de m².K/W) selon le sens du flux */
    public static function rsi(TypeSurface $type): int
    {
        return match ($type) {
            TypeSurface::PlafondPan => 100,
            TypeSurface::Sol => 170,
            default => 130,
        };
    }

    public static function rse(TypeSurface $type): int
    {
        return 40;
    }

    public static function totale(TypeSurface $type, int $rParoiExistanteMilli, int $rSystemeMilli): int
    {
        return self::rsi($type) + $rParoiExistanteMilli + $rSystemeMilli + self::rse($type);
    }

    /**
     * U = 1 / RT, en millièmes de W/m².K (arrondi à l'entier).
     */
    public static function coefficientU(int $rTotaleMilli): int
    {
        if ($rTotaleMilli <= 0) {
            throw new \InvalidArgumentException('The total thermal resistance must be positive.');
        }

        return (int) round(1_000_000 / $rTotaleMilli);
    }
}
