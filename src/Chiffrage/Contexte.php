<?php

declare(strict_types=1);

namespace App\Chiffrage;

use App\Catalogue\MaterielLisible;
use App\Entity\Fournisseur;

/**
 * Données d'un chiffrage utiles aux stratégies : fournisseur imposé, position du chantier, marge de sécurité par
 * article (centièmes de pourcent) et choix manuels (référence d'article → fournisseur).
 */
final readonly class Contexte
{
    /**
     * @param (\Closure(MaterielLisible): int)|null $marge
     * @param array<string, Fournisseur>             $choix
     */
    public function __construct(
        public ?Fournisseur $fournisseurImpose = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        private ?\Closure $marge = null,
        public array $choix = [],
    ) {
    }

    public function margeCentiemes(MaterielLisible $article): int
    {
        return null === $this->marge ? 0 : ($this->marge)($article);
    }

    public function aUnePosition(): bool
    {
        return null !== $this->latitude && null !== $this->longitude;
    }
}
