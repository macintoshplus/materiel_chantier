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
 * Fournisseur choisi par l'utilisateur pour chaque article (par référence) ; sans choix, ligne « prix manquant ».
 */
final readonly class Manuelle implements StrategieFournisseur
{
    use ChoixParArticle;

    public function __construct(private SourcePrix $source)
    {
    }

    public function choisir(Materiau $materiau, QuantiteBrute $quantite, \DateTimeImmutable $date, Contexte $contexte): ?PrixMateriau
    {
        $fournisseur = $contexte->choix[$materiau->getReference()] ?? null;

        return null === $fournisseur ? null : $this->source->prix($materiau, $fournisseur, $date);
    }
}
