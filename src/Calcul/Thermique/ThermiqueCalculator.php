<?php

declare(strict_types=1);

namespace App\Calcul\Thermique;

use App\Enum\TypeSurface;

/**
 * RT = Rsi + R(paroi existante) + somme des R des couches du système + Rse. Pour une cloison (`cloison_face`),
 * les deux côtés sont des ambiances intérieures : Rsi des deux côtés, sans Rse ; le résultat sert à l'acoustique
 * et au confort, pas à l'objectif d'isolation d'une paroi extérieure. Le pont thermique d'ossature n'est pas
 * pris en compte (option v2). Valeurs en millièmes de m².K/W ; U en millièmes de W/m².K.
 */
final class ThermiqueCalculator
{
    /**
     * @param int             $rParoiExistanteMilli R de la paroi existante (couches ou forfait)
     * @param array<int, int> $rCouchesMilli        R de chaque couche du système (clé : ordre) ; une membrane vaut 0
     */
    public function resistanceTotale(TypeSurface $type, int $rParoiExistanteMilli, array $rCouchesMilli): BilanThermique
    {
        $rsi = ResistanceThermique::rsi($type);
        $coteOppose = ResistanceThermique::rse($type);
        $total = ResistanceThermique::totale($type, $rParoiExistanteMilli, array_sum($rCouchesMilli));

        return new BilanThermique(
            $rsi,
            $coteOppose,
            $rParoiExistanteMilli,
            $rCouchesMilli,
            $total,
            ResistanceThermique::coefficientU($total),
            TypeSurface::CloisonFace === $type,
        );
    }
}
