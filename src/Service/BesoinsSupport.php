<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Agregation\BesoinsProjet;
use App\Entity\Cloison;
use App\Entity\Piece;
use App\Entity\Surface;

/**
 * Besoins bruts du système actif d'un support (surface ou cloison), avant toute agrégation.
 */
final readonly class BesoinsSupport
{
    public function __construct(
        public Piece $piece,
        public Surface|Cloison $support,
        public BesoinsProjet $besoins,
    ) {
    }

    public function nom(): string
    {
        return $this->piece->getNom().' / '.$this->support->getNom();
    }
}
