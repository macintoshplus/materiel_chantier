<?php

declare(strict_types=1);

namespace App\Geometrie;

use App\Enum\TypeAncrage;

/**
 * Candidat à l'ancrage : axe de la cible au sol (mur : son arête ; cloison : son axe), épaisseur, et arête basse
 * (identifiants de sommets) de la surface qui reçoit la jonction.
 */
final readonly class CibleAncrage
{
    public function __construct(
        public TypeAncrage $type,
        public ?int $id,
        public string $cle,
        public Segment $axe,
        public int $epaisseur = 0,
        public ?string $idDebut = null,
        public ?string $idFin = null,
    ) {
    }
}
