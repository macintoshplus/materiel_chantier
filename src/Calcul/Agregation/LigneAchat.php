<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

use App\Calcul\Couche\TypeBesoin;
use App\Calcul\Substitution\NatureOffre;
use App\Catalogue\MaterielLisible;

/**
 * Achat d'un article pour l'ensemble des besoins agrégés : unités après optimisation (barres, plaques), marge de
 * sécurité, lots entiers et chute totale. `quantiteAvecMargeMilli` est en millièmes d'unité (12,1 plaques = 12100).
 * `chuteOptimisation` est la chute de découpe (mm, mm²) ; `chute` est le surplus d'unités achetées par rapport au
 * besoin optimisé (marge et arrondi au lot).
 */
final readonly class LigneAchat
{
    /**
     * @param list<string> $cles
     * @param list<string> $origines
     */
    public function __construct(
        public MaterielLisible $article,
        public ?MaterielLisible $articleInitial,
        public TypeBesoin $type,
        public array $cles,
        public array $origines,
        public int $besoin,
        public int $unitesBesoin,
        public int $chuteOptimisation,
        public int $margeCentiemes,
        public SourceMarge $sourceMarge,
        public int $quantiteAvecMargeMilli,
        public int $unitesParLot,
        public int $lots,
        public int $quantiteAchetee,
        public int $chute,
        public NatureOffre $nature = NatureOffre::Exacte,
        public bool $prixManquant = false,
    ) {
    }

    public function estSubstitue(): bool
    {
        return null !== $this->articleInitial;
    }
}
