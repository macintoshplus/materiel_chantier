<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Ce que représente le contour dessiné : la face intérieure du mur (aucun décalage), l'axe du mur (décalage d'une
 * demi-épaisseur vers l'intérieur) ou la face extérieure (décalage d'une épaisseur).
 */
enum ModeContour: string
{
    case Interieur = 'interieur';
    case Axe = 'axe';
    case Exterieur = 'exterieur';

    public function decalage(int $epaisseurMur): int
    {
        return match ($this) {
            self::Interieur => 0,
            self::Axe => intdiv($epaisseurMur, 2),
            self::Exterieur => $epaisseurMur,
        };
    }
}
