<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

use App\Calcul\Materiau\SourceCandidats;

/**
 * Un autre article satisfaisant la même spécification, vendu par le fournisseur demandé : le premier candidat
 * (ordre du catalogue) qu'il propose. Aucun équivalent sans autorisation ni spécification résolue.
 */
final readonly class OffreEquivalente implements MaillonOffre
{
    public function __construct(private SourceCandidats $candidats, private SourceOffres $offres)
    {
    }

    public function resoudre(DemandeOffre $demande): ?Offre
    {
        if (!$demande->equivalentsAutorises || null === $demande->specification || !$demande->specification->estResolue()) {
            return null;
        }
        foreach ($this->candidats->candidats($demande->specification) as $candidat) {
            if ($candidat === $demande->epingle) {
                continue;
            }
            $offre = $this->offres->offre($candidat, $demande->fournisseur);
            if (null !== $offre) {
                return $offre->avecNature(NatureOffre::Equivalente);
            }
        }

        return null;
    }
}
