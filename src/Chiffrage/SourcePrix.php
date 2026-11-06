<?php

declare(strict_types=1);

namespace App\Chiffrage;

use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;

/**
 * Ce que les fournisseurs vendent : le prix valide à une date de l'article chez un fournisseur (l'article lui-même,
 * sinon un équivalent : `PrixMateriau::getMateriau()` donne l'article réellement proposé).
 */
interface SourcePrix
{
    /**
     * @return list<Fournisseur> fournisseurs actifs, dans un ordre stable
     */
    public function fournisseurs(): array;

    public function prix(Materiau $article, Fournisseur $fournisseur, \DateTimeImmutable $date): ?PrixMateriau;
}
