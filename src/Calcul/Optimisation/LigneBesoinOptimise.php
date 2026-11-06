<?php

declare(strict_types=1);

namespace App\Calcul\Optimisation;

use App\Catalogue\MaterielLisible;

/**
 * Besoin optimisé pour un article précis, sans prix : unités achetées (barres, plaques, pièces), lots et chute.
 * `chute` est en mm pour les pièces/longueurs/surfaces (mm²) et en unités pour un nombre.
 */
final readonly class LigneBesoinOptimise
{
    public function __construct(
        public string $cle,
        public string $emplacement,
        public MaterielLisible $materiau,
        public int $besoin,
        public int $unitesAchetees,
        public int $lots,
        public int $chute,
    ) {
    }
}
