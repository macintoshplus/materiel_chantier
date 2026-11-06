<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

use App\Calcul\Couche\TypeBesoin;

/**
 * Besoin sans article de catalogue (par exemple un adhésif exprimé en longueur) : cumulé par clé, sans lot ni marge.
 */
final readonly class BesoinSansArticle
{
    public function __construct(
        public string $cle,
        public string $categorie,
        public TypeBesoin $type,
        public int $quantite,
    ) {
    }
}
