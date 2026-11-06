<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;

/**
 * Substitution pour un fournisseur choisi : délègue à `ResolveurOffre`. La spécification d'un article (pour chercher
 * un équivalent) vient du `fournisseurSpecification` ; sans elle, seule la correspondance exacte est possible.
 */
final readonly class SubstitutionFournisseur implements SubstitutionArticle
{
    /**
     * @param \Closure(MaterielLisible): ?MaterielSpecification $specification
     * @param list<int>                                         $autresFournisseurs
     */
    public function __construct(
        private ResolveurOffre $resolveur,
        private int $fournisseur,
        private \Closure $specification,
        private bool $equivalentsAutorises = true,
        private array $autresFournisseurs = [],
    ) {
    }

    public function retenir(MaterielLisible $article): ResultatOffre
    {
        return $this->resolveur->resoudre(new DemandeOffre($article, ($this->specification)($article), $this->fournisseur, $this->equivalentsAutorises, $this->autresFournisseurs));
    }
}
