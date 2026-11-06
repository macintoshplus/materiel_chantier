<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

/**
 * Position d'un élément (ou d'un passage) dans le repère de sa pièce, en mm, avec la hauteur du plafond à sa verticale.
 */
final readonly class PointCheminement
{
    public function __construct(
        public string $piece,
        public int $x,
        public int $y,
        public int $z,
        public int $hauteurPlafond,
    ) {
    }
}
