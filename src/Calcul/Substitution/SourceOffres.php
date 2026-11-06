<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

use App\Catalogue\MaterielLisible;

/**
 * Ce qu'un fournisseur vend : l'offre d'un article précis, ou `null` s'il ne le propose pas.
 */
interface SourceOffres
{
    public function offre(MaterielLisible $article, int $fournisseur): ?Offre;
}
