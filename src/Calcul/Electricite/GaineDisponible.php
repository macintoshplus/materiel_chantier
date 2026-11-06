<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

/**
 * Gaine du catalogue : diamètre extérieur (mm), taux de remplissage maximal (%) pour lequel les nombres maximaux de
 * conducteurs ont été établis, et nombre maximal de conducteurs par section (centièmes de mm²).
 */
final readonly class GaineDisponible
{
    /**
     * @param array<int, int> $conducteursMax nombre maximal de conducteurs, indexé par section en centièmes de mm²
     */
    public function __construct(public int $diametre, public int $tauxRemplissageMax, public array $conducteursMax)
    {
    }

    public function admet(int $sectionCentiemes, int $conducteurs): bool
    {
        return isset($this->conducteursMax[$sectionCentiemes]) && $conducteurs <= $this->conducteursMax[$sectionCentiemes];
    }
}
