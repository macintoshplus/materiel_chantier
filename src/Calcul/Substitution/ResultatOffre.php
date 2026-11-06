<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

use App\Geometrie\Avertissement;

/**
 * Résultat de la chaîne : l'offre retenue, ou `null` (« prix manquant ») avec l'avertissement correspondant.
 */
final readonly class ResultatOffre
{
    /**
     * @param list<Avertissement> $avertissements
     */
    public function __construct(public ?Offre $offre, public array $avertissements = [])
    {
    }

    public function estManquant(): bool
    {
        return null === $this->offre;
    }
}
