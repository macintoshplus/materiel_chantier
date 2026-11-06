<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Electricite\ElementReseau;
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
     * @param array<int, ResultatCheminement>   $cheminementParReseau cheminement de chaque réseau calculé, indexé par `spl_object_id` du réseau
     * @param array<int, list<ElementReseau>>   $elementsParReseau    éléments de chaque réseau calculé (point de départ compris), même indexation
     */
    public function __construct(
        public array $supports,
        public array $specifications,
        public array $cheminements,
        public array $avertissements,
        public array $erreurs,
        public array $cheminementParReseau = [],
        public array $elementsParReseau = [],
    ) {
    }
}
