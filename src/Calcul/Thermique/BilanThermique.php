<?php

declare(strict_types=1);

namespace App\Calcul\Thermique;

/**
 * Bilan thermique d'une paroi, en entiers : résistances en millièmes de m².K/W, U en millièmes de W/m².K.
 * `rCouchesMilli` donne le R de chaque couche du système (clé : ordre de la couche). Pour une cloison,
 * `rCoteOpposeMilli` est le Rsi du second côté (ambiance intérieure) et non un Rse.
 */
final readonly class BilanThermique
{
    /**
     * @param array<int, int> $rCouchesMilli
     */
    public function __construct(
        public int $rsiMilli,
        public int $rCoteOpposeMilli,
        public int $rParoiExistanteMilli,
        public array $rCouchesMilli,
        public int $rTotaleMilli,
        public int $uMilli,
        public bool $deuxCotesInterieurs,
    ) {
    }

    /** Écart entre le R total et l'objectif de résistance saisi (millièmes de m².K/W) : positif si l'objectif est dépassé. */
    public function deltaObjectifMilli(int $objectifRMilli): int
    {
        return $this->rTotaleMilli - $objectifRMilli;
    }
}
