<?php

declare(strict_types=1);

namespace App\Calcul\Optimisation;

use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\TypeBesoin;
use App\Catalogue\MaterielLisible;

/**
 * Transforme un besoin brut en achat pour un article précis (ses dimensions, son lot). Rappelé quand l'article
 * change (substitution par un équivalent). Sans prix ni marge (voir 05e et 06).
 */
final class OptimiseurBesoins
{
    public function optimiser(LigneBesoin $besoin, MaterielLisible $article): LigneBesoinOptimise
    {
        $parLot = max(1, $this->entier($article, 'unites_par_lot', 1));

        [$unites, $chute] = match ($besoin->type) {
            TypeBesoin::Pieces => $this->pieces($besoin->pieces, $this->requis($article, 'longueur')),
            TypeBesoin::Longueur => $this->longueur($besoin->quantite, $this->requis($article, 'longueur')),
            TypeBesoin::Surface => $this->surface($besoin->quantite, $this->requis($article, 'longueur') * $this->requis($article, 'largeur')),
            TypeBesoin::Nombre => [$besoin->quantite, 0],
        };

        $lots = (int) ceil($unites / $parLot);
        if (TypeBesoin::Nombre === $besoin->type) {
            $chute = $lots * $parLot - $besoin->quantite;
        }

        return new LigneBesoinOptimise($besoin->cle, $besoin->emplacement, $article, $besoin->quantite, $unites, $lots, $chute);
    }

    /**
     * @param list<int> $pieces
     *
     * @return array{int, int}
     */
    private function pieces(array $pieces, int $longueurBarre): array
    {
        $barres = OptimiseurCoupes::barres($pieces, $longueurBarre);

        return [$barres, $barres * $longueurBarre - array_sum($pieces)];
    }

    /**
     * @return array{int, int}
     */
    private function longueur(int $longueur, int $longueurBarre): array
    {
        $barres = (int) ceil($longueur / $longueurBarre);

        return [$barres, $barres * $longueurBarre - $longueur];
    }

    /**
     * @return array{int, int}
     */
    private function surface(int $surface, int $surfaceArticle): array
    {
        $unites = (int) ceil($surface / $surfaceArticle);

        return [$unites, $unites * $surfaceArticle - $surface];
    }

    private function requis(MaterielLisible $article, string $cle): int
    {
        $v = $article->valeur($cle);
        if (!\is_int($v) || $v <= 0) {
            throw new \InvalidArgumentException(\sprintf('The article has no usable "%s".', $cle));
        }

        return $v;
    }

    private function entier(MaterielLisible $article, string $cle, int $defaut): int
    {
        $v = $article->valeur($cle);

        return \is_int($v) ? $v : $defaut;
    }
}
