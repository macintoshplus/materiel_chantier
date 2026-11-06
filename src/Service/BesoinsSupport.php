<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Agregation\BesoinsProjet;
use App\Entity\Cloison;
use App\Entity\Piece;
use App\Entity\Surface;

/**
 * Besoins bruts du système actif d'un support (surface ou cloison), avant toute agrégation. Sans support (`null`), ce
 * sont les besoins annexes d'une pièce, comme l'installation électrique, désignés par `libelle`.
 */
final readonly class BesoinsSupport
{
    public const string LIBELLE_ELECTRICITE = 'Electrical installation';

    public function __construct(
        public Piece $piece,
        public Surface|Cloison|null $support,
        public BesoinsProjet $besoins,
        public string $libelle = self::LIBELLE_ELECTRICITE,
    ) {
    }

    public function nom(): string
    {
        return $this->piece->getNom().' / '.$this->nomSupport();
    }

    public function nomSupport(): string
    {
        return $this->support?->getNom() ?? $this->libelle;
    }
}
