<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

use App\Catalogue\MaterielLisible;

/**
 * Article proposé par un fournisseur, avec la taille de lot de son prix si elle diffère de celle de l'article.
 */
final readonly class Offre
{
    public function __construct(
        public MaterielLisible $article,
        public int $fournisseur,
        public ?int $unitesParLot = null,
        public NatureOffre $nature = NatureOffre::Exacte,
    ) {
    }

    public function avecNature(NatureOffre $nature): self
    {
        return new self($this->article, $this->fournisseur, $this->unitesParLot, $nature);
    }
}
