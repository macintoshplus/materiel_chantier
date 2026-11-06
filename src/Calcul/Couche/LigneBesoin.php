<?php

declare(strict_types=1);

namespace App\Calcul\Couche;

use App\Catalogue\MaterielLisible;
use App\Geometrie\Direction;

/**
 * Besoin brut d'une couche, sans prix ni optimisation : l'article précis (`materiau`) intervient plus tard,
 * quand l'optimisation est exécutée pour lui (voir `OptimiseurBesoins`).
 */
final readonly class LigneBesoin
{
    /**
     * @param list<int>          $pieces          longueurs (mm) des pièces à découper (type `Pieces`)
     * @param array<string, int> $caracteristiques valeurs entières propres à la ligne (ex. `longueur_mm` d'une vis)
     */
    public function __construct(
        public string $cle,
        public string $emplacement,
        public string $categorie,
        public TypeBesoin $type,
        public int $quantite,
        public array $pieces = [],
        public ?Direction $direction = null,
        public array $caracteristiques = [],
        public ?MaterielLisible $materiau = null,
    ) {
    }

    /**
     * @param list<int>          $pieces
     * @param array<string, int> $caracteristiques
     */
    public static function pieces(string $cle, string $emplacement, string $categorie, array $pieces, ?Direction $direction = null, array $caracteristiques = [], ?MaterielLisible $materiau = null): self
    {
        return new self($cle, $emplacement, $categorie, TypeBesoin::Pieces, \count($pieces), $pieces, $direction, $caracteristiques, $materiau);
    }

    /**
     * @param array<string, int> $caracteristiques
     */
    public static function nombre(string $cle, string $emplacement, string $categorie, int $nombre, array $caracteristiques = [], ?MaterielLisible $materiau = null): self
    {
        return new self($cle, $emplacement, $categorie, TypeBesoin::Nombre, $nombre, [], null, $caracteristiques, $materiau);
    }

    public static function surface(string $cle, string $emplacement, string $categorie, int $surfaceMm2, ?MaterielLisible $materiau = null): self
    {
        return new self($cle, $emplacement, $categorie, TypeBesoin::Surface, $surfaceMm2, [], null, [], $materiau);
    }
}
