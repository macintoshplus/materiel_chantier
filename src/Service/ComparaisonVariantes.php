<?php

declare(strict_types=1);

namespace App\Service;

use App\Geometrie\Avertissement;

/**
 * Une ligne de la comparaison des variantes d'un support. Résistances en millièmes de m².K/W, U en millièmes de
 * W/m².K, épaisseur en mm. Le coût est `null` tant que le chiffrage (étape 06) n'est pas en place.
 */
final readonly class ComparaisonVariantes
{
    /**
     * @param list<Avertissement> $erreurs
     */
    public function __construct(
        public int $systemeId,
        public string $support,
        public string $definition,
        public string $variante,
        public bool $actif,
        public ?int $rTotaleMilli,
        public ?int $uMilli,
        public ?int $epaisseurMm,
        public ?int $coutCentimes = null,
        public array $erreurs = [],
    ) {
    }

    public function estValide(): bool
    {
        return [] === $this->erreurs;
    }
}
