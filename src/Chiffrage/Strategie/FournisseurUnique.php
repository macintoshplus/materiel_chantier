<?php

declare(strict_types=1);

namespace App\Chiffrage\Strategie;

use App\Chiffrage\Contexte;
use App\Chiffrage\QuantiteBrute;
use App\Chiffrage\SourcePrix;
use App\Chiffrage\StrategieFournisseur;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;

/**
 * Tout chez le fournisseur imposé (article exact, sinon équivalent chez lui) ; jamais de repli sur un autre.
 */
final readonly class FournisseurUnique implements StrategieFournisseur
{
    use ChoixParArticle;

    public function __construct(private SourcePrix $source)
    {
    }

    public function choisir(Materiau $materiau, QuantiteBrute $quantite, \DateTimeImmutable $date, Contexte $contexte): ?PrixMateriau
    {
        return null === $contexte->fournisseurImpose ? null : $this->source->prix($materiau, $contexte->fournisseurImpose, $date);
    }
}
