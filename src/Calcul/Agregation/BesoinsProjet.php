<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

use App\Calcul\Couche\LigneBesoin;

/**
 * Agrégation des besoins bruts de toutes les surfaces par article (et type de besoin) : les pièces à découper
 * sont réunies avant l'optimisation, pour que la chute d'une surface serve à une autre. Sans prix ni marge.
 */
final class BesoinsProjet
{
    /** @var array<string, BesoinArticle> */
    private array $articles = [];

    /** @var array<string, BesoinSansArticle> */
    private array $sansArticle = [];

    /**
     * @param iterable<LigneBesoin> $lignes
     */
    public function ajouter(iterable $lignes, string $origine = ''): void
    {
        foreach ($lignes as $ligne) {
            $article = $ligne->materiau;
            if (null === $article) {
                $this->ajouterSansArticle($ligne);

                continue;
            }
            $besoin = BesoinArticle::depuis($ligne, $article, $origine);
            $cle = spl_object_id($article).'|'.$ligne->type->value;
            $this->articles[$cle] = isset($this->articles[$cle]) ? $this->articles[$cle]->fusionner($besoin) : $besoin;
        }
    }

    /**
     * Ajoute un besoin déjà cumulé (même clé : article et type de besoin), sans perdre ses origines.
     */
    public function ajouterBesoin(BesoinArticle $besoin): void
    {
        $cle = spl_object_id($besoin->article).'|'.$besoin->type->value;
        $this->articles[$cle] = isset($this->articles[$cle]) ? $this->articles[$cle]->fusionner($besoin) : $besoin;
    }

    /**
     * Réunit les besoins d'un autre cumul (par exemple celui d'une surface) à celui-ci.
     */
    public function reunir(self $autre): void
    {
        foreach ($autre->articles as $besoin) {
            $this->ajouterBesoin($besoin);
        }
        foreach ($autre->sansArticle as $cle => $sans) {
            $this->sansArticle[$cle] = new BesoinSansArticle($sans->cle, $sans->categorie, $sans->type, ($this->sansArticle[$cle]->quantite ?? 0) + $sans->quantite);
        }
    }

    /**
     * @return list<BesoinArticle>
     */
    public function articles(): array
    {
        return array_values($this->articles);
    }

    /**
     * @return list<BesoinSansArticle>
     */
    public function sansArticle(): array
    {
        return array_values($this->sansArticle);
    }

    public function estVide(): bool
    {
        return [] === $this->articles && [] === $this->sansArticle;
    }

    private function ajouterSansArticle(LigneBesoin $ligne): void
    {
        $cle = $ligne->cle.'|'.$ligne->categorie.'|'.$ligne->type->value;
        $this->sansArticle[$cle] = new BesoinSansArticle(
            $ligne->cle,
            $ligne->categorie,
            $ligne->type,
            ($this->sansArticle[$cle]->quantite ?? 0) + $ligne->quantite,
        );
    }
}
