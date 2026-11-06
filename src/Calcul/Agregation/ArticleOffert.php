<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

use App\Catalogue\MaterielLisible;

/**
 * Article dont la taille de lot est celle de l'offre retenue (le prix du fournisseur peut surcharger celle de
 * l'article). Le reste est délégué à l'article.
 */
final readonly class ArticleOffert implements MaterielLisible
{
    public function __construct(public MaterielLisible $article, private int $unitesParLot)
    {
    }

    public function categorie(): string
    {
        return $this->article->categorie();
    }

    public function valeur(string $chemin): mixed
    {
        return 'unites_par_lot' === $chemin ? $this->unitesParLot : $this->article->valeur($chemin);
    }
}
