<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\TypeBesoin;
use App\Catalogue\MaterielLisible;

/**
 * Besoins bruts cumulés d'un article et d'un type de besoin (toutes surfaces confondues) : pièces à découper,
 * ou quantité (nombre, longueur en mm, surface en mm²). L'optimisation n'a pas encore eu lieu.
 */
final readonly class BesoinArticle
{
    /**
     * @param list<int>    $pieces
     * @param list<string> $cles    clés des lignes de besoin d'origine
     * @param list<string> $origines supports (surfaces, cloisons) à l'origine du besoin
     */
    public function __construct(
        public MaterielLisible $article,
        public TypeBesoin $type,
        public int $quantite,
        public array $pieces,
        public string $categorie,
        public array $cles,
        public array $origines,
    ) {
    }

    public static function depuis(LigneBesoin $ligne, MaterielLisible $article, string $origine): self
    {
        return new self($article, $ligne->type, $ligne->quantite, $ligne->pieces, $ligne->categorie, [$ligne->cle], '' === $origine ? [] : [$origine]);
    }

    public function fusionner(self $autre): self
    {
        $pieces = [...$this->pieces, ...$autre->pieces];

        return new self(
            $this->article,
            $this->type,
            TypeBesoin::Pieces === $this->type ? \count($pieces) : $this->quantite + $autre->quantite,
            $pieces,
            $this->categorie,
            array_values(array_unique([...$this->cles, ...$autre->cles])),
            array_values(array_unique([...$this->origines, ...$autre->origines])),
        );
    }

    public function versLigne(?MaterielLisible $article = null): LigneBesoin
    {
        $article ??= $this->article;
        $cle = implode('+', $this->cles);
        $emplacement = 'agregat';

        return TypeBesoin::Pieces === $this->type
            ? LigneBesoin::pieces($cle, $emplacement, $this->categorie, $this->pieces, null, [], $article)
            : new LigneBesoin($cle, $emplacement, $this->categorie, $this->type, $this->quantite, [], null, [], $article);
    }
}
