<?php

declare(strict_types=1);

namespace App\Chiffrage;

use App\Entity\Materiau;

/**
 * Besoin d'un article avant marge et lots, dans son unité d'achat (barres, plaques, m² d'isolant…). Un candidat de
 * dimensions différentes (rail de 2,6 m au lieu de 3 m) a un autre nombre d'unités : l'optimisation de coupes est
 * alors refaite par `$recalcul`.
 */
final readonly class QuantiteBrute
{
    /**
     * @param (\Closure(Materiau): int)|null $recalcul unités brutes pour un autre article
     */
    public function __construct(
        public Materiau $origine,
        public int $unites,
        private ?\Closure $recalcul = null,
    ) {
    }

    public function pour(Materiau $candidat): int
    {
        if ($candidat === $this->origine || null === $this->recalcul || $this->memesDimensions($candidat)) {
            return $this->unites;
        }

        return ($this->recalcul)($candidat);
    }

    private function memesDimensions(Materiau $candidat): bool
    {
        $o = $this->origine;

        return $o->getLongueur() === $candidat->getLongueur() && $o->getLargeur() === $candidat->getLargeur() && $o->getHauteur() === $candidat->getHauteur();
    }
}
