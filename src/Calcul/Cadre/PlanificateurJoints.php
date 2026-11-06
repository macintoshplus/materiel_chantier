<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

/**
 * Position des joints d'un montant doublé « décalé » : deux lignes de barres aboutées couvrant toute la longueur.
 * Stratégie v1 : première ligne coupée à la longueur de barre, seconde ligne coupée à la moitié, puis ajustée
 * pour respecter l'écart minimal entre les joints des deux lignes.
 */
final class PlanificateurJoints
{
    private const int PAS_AJUSTEMENT = 10;

    /**
     * @return list<list<int>> positions des joints (mm depuis le début du montant), une liste par ligne
     */
    public static function joints(int $longueur, int $longueurBarre, int $decalageMin): array
    {
        if ($longueurBarre <= 0) {
            throw new \InvalidArgumentException('The bar length must be positive.');
        }
        if ($longueur <= $longueurBarre) {
            return [[], []];
        }
        $ligne1 = [];
        for ($p = $longueurBarre; $p < $longueur; $p += $longueurBarre) {
            $ligne1[] = $p;
        }

        $ligne2 = [];
        $precedent = 0;
        $p = intdiv($longueurBarre, 2);
        while ($p < $longueur) {
            $p = self::ajuster($p, $precedent, $longueur, $longueurBarre, $decalageMin, $ligne1);
            if ($p >= $longueur) {
                break;
            }
            $ligne2[] = $p;
            $precedent = $p;
            $p += $longueurBarre;
        }

        return [$ligne1, $ligne2];
    }

    /**
     * Segments (début, fin) d'une ligne à partir de ses joints.
     *
     * @param list<int> $joints
     *
     * @return list<int> longueurs des segments
     */
    public static function segments(int $longueur, array $joints): array
    {
        $segments = [];
        $debut = 0;
        foreach ($joints as $joint) {
            $segments[] = $joint - $debut;
            $debut = $joint;
        }
        $segments[] = $longueur - $debut;

        return $segments;
    }

    /**
     * @param list<int> $autre joints de l'autre ligne
     */
    private static function ajuster(int $p, int $precedent, int $longueur, int $barre, int $decalageMin, array $autre): int
    {
        for ($k = 0; $k <= $barre; $k += self::PAS_AJUSTEMENT) {
            foreach ([$p + $k, $p - $k] as $candidat) {
                if ($candidat <= $precedent || $candidat - $precedent > $barre) {
                    continue;
                }
                if (self::ecartOk($candidat, $autre, $decalageMin)) {
                    return $candidat;
                }
            }
        }

        return $p;
    }

    /**
     * @param list<int> $autre
     */
    private static function ecartOk(int $p, array $autre, int $decalageMin): bool
    {
        foreach ($autre as $q) {
            if (abs($p - $q) < $decalageMin) {
                return false;
            }
        }

        return true;
    }
}
