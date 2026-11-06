<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

use App\Geometrie\Avertissement;

/**
 * Résultat du résolveur : configuration retenue (null si aucune ne convient), configurations possibles,
 * avertissements et éventuelle erreur bloquante.
 */
final readonly class ResolutionOssature
{
    /**
     * @param list<ConfigurationOssature> $candidates
     * @param list<Avertissement>         $avertissements
     */
    public function __construct(
        public ?ConfigurationOssature $configuration,
        public array $candidates,
        public array $avertissements = [],
        public ?Avertissement $erreur = null,
    ) {
    }
}
