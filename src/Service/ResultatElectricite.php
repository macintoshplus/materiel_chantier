<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Electricite\ResultatCheminement;
use App\Catalogue\MaterielSpecification;
use App\Geometrie\Avertissement;

/**
 * Installation électrique d'un projet : besoins annexes par pièce (prêts à rejoindre la nomenclature), cheminement de
 * chaque réseau, spécifications des articles résolus (pour la substitution) et messages.
 */
final readonly class ResultatElectricite
{
    /**
     * @param list<BesoinsSupport>              $supports
     * @param array<int, MaterielSpecification> $specifications indexées par `spl_object_id` de l'article
     * @param list<ResultatCheminement>         $cheminements
     * @param list<Avertissement>               $avertissements
     * @param list<Avertissement>               $erreurs
     */
    public function __construct(
        public array $supports,
        public array $specifications,
        public array $cheminements,
        public array $avertissements,
        public array $erreurs,
    ) {
    }
}
