<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

/**
 * Maillon de la chaîne de résolution (`ResolveurOffre`) : une offre, ou `null` pour passer au maillon suivant.
 */
interface MaillonOffre
{
    public function resoudre(DemandeOffre $demande): ?Offre;
}
