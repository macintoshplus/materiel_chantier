<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

/**
 * Tronçon entre deux éléments : longueur de gaine (mm), longueur de câble avec réserve et queues (mm), conducteurs.
 */
final readonly class Troncon
{
    public function __construct(
        public string $parent,
        public string $enfant,
        public int $longueurMm,
        public int $conducteursActifs,
        public bool $terre,
        public int $longueurCableMm,
        public ?int $diametreGaine,
    ) {
    }

    public function conducteurs(): int
    {
        return $this->conducteursActifs + ($this->terre ? 1 : 0);
    }
}
