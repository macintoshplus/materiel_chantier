<?php

declare(strict_types=1);

namespace App\Calcul\Pipeline;

use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Catalogue\MaterielLisible;
use App\Geometrie\Avertissement;

final readonly class ResultatPipeline
{
    /**
     * @param list<LigneBesoin>               $lignes
     * @param list<Avertissement>             $avertissements
     * @param list<Avertissement>             $erreurs        bloquantes : aucun calcul n'a été fait
     * @param array<int, MaterielLisible|null> $materiaux      matériau retenu par ordre de couche
     */
    public function __construct(
        public array $lignes,
        public ContexteCouches $contexte,
        public array $avertissements = [],
        public array $erreurs = [],
        public array $materiaux = [],
    ) {
    }

    public function estValide(): bool
    {
        return [] === $this->erreurs;
    }
}
