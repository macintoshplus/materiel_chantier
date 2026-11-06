<?php

declare(strict_types=1);

namespace App\Calcul\Couche;

use App\Geometrie\Avertissement;

final readonly class ResultatCouche
{
    /**
     * @param list<LigneBesoin>  $lignes
     * @param list<Avertissement> $avertissements
     */
    public function __construct(
        public array $lignes,
        public ContexteCouches $contexte,
        public array $avertissements = [],
    ) {
    }
}
