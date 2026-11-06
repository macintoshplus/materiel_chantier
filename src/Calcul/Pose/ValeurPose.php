<?php

declare(strict_types=1);

namespace App\Calcul\Pose;

use App\Geometrie\Avertissement;

/**
 * Valeur retenue d'un paramètre de pose et son origine. `valeur` est null (avec une erreur) quand aucune donnée n'existe.
 */
final readonly class ValeurPose
{
    /**
     * @param list<Avertissement> $avertissements
     */
    public function __construct(
        public string $nom,
        public ?int $valeur,
        public ?SourceValeur $source,
        public array $avertissements = [],
        public ?Avertissement $erreur = null,
    ) {
    }
}
