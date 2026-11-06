<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

/**
 * L'article épinglé chez le fournisseur demandé.
 */
final readonly class OffreExacte implements MaillonOffre
{
    public function __construct(private SourceOffres $offres)
    {
    }

    public function resoudre(DemandeOffre $demande): ?Offre
    {
        return $this->offres->offre($demande->epingle, $demande->fournisseur)?->avecNature(NatureOffre::Exacte);
    }
}
