<?php

declare(strict_types=1);

namespace App\Service;

use App\Catalogue\MaterielSpecification;
use App\Geometrie\Avertissement;

/**
 * Premier temps du calcul d'un projet : les besoins bruts de chaque support (système actif), les spécifications des
 * articles épinglés (pour chercher des équivalents) et les avertissements de calcul. Aucune agrégation ni prix.
 */
final readonly class PreparationCalcul
{
    /**
     * @param list<BesoinsSupport>                $supports
     * @param array<int, MaterielSpecification>   $specifications indexées par `spl_object_id` de l'article
     * @param list<Avertissement>                 $avertissements
     * @param list<Avertissement>                 $erreurs
     */
    public function __construct(
        public array $supports,
        public array $specifications,
        public array $avertissements,
        public array $erreurs,
    ) {
    }
}
