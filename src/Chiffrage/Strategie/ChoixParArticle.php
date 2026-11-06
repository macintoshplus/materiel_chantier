<?php

declare(strict_types=1);

namespace App\Chiffrage\Strategie;

use App\Chiffrage\Besoin;
use App\Chiffrage\Contexte;

/**
 * `choisirEnsemble()` des stratégies indépendantes d'un article à l'autre : itération sur `choisir()`.
 */
trait ChoixParArticle
{
    /**
     * @param list<Besoin> $besoins
     *
     * @return list<?\App\Entity\PrixMateriau>
     */
    public function choisirEnsemble(array $besoins, \DateTimeImmutable $date, Contexte $contexte): array
    {
        return array_map(fn (Besoin $b) => $this->choisir($b->materiau, $b->quantite, $date, $contexte), $besoins);
    }
}
