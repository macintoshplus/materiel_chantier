<?php

declare(strict_types=1);

namespace App\Calcul\Calepinage;

/**
 * Plaque (ou chute de plaque) posée dans le repère de la surface : coin bas-gauche et dimensions en mm,
 * colonne et ligne de la grille (à partir de 0).
 */
final readonly class PlaqueCalepinee
{
    public function __construct(
        public int $x,
        public int $y,
        public int $largeur,
        public int $hauteur,
        public int $colonne,
        public int $ligne,
    ) {
    }
}
