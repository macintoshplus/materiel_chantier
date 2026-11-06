<?php

declare(strict_types=1);

namespace App\Chiffrage;

use App\Calcul\Substitution\Offre;
use App\Calcul\Substitution\ResultatOffre;
use App\Calcul\Substitution\SubstitutionArticle;
use App\Catalogue\MaterielLisible;

/**
 * Impose un article candidat (sans fournisseur) pour recalculer l'optimisation avec ses dimensions.
 */
final readonly class SubstitutionForcee implements SubstitutionArticle
{
    public function __construct(private MaterielLisible $candidat)
    {
    }

    public function retenir(MaterielLisible $article): ResultatOffre
    {
        return new ResultatOffre(new Offre($this->candidat, 0));
    }
}
