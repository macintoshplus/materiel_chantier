<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

use App\Geometrie\Avertissement;

final readonly class ResultatAgregation
{
    /**
     * @param list<LigneAchat>        $achats
     * @param list<BesoinSansArticle> $sansArticle
     * @param list<Avertissement>     $avertissements
     */
    public function __construct(
        public array $achats,
        public array $sansArticle = [],
        public array $avertissements = [],
    ) {
    }
}
