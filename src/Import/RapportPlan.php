<?php

declare(strict_types=1);

namespace App\Import;

use App\Geometrie\Avertissement;

/**
 * Ce que la lecture du plan a ignoré ou signalé.
 */
final readonly class RapportPlan
{
    /**
     * @param array<string, int>    $ignores       type d'entité => nombre d'entités ignorées
     * @param list<Avertissement>   $avertissements
     */
    public function __construct(public array $ignores = [], public array $avertissements = [])
    {
    }
}
