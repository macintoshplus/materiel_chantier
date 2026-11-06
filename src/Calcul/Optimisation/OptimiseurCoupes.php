<?php

declare(strict_types=1);

namespace App\Calcul\Optimisation;

/**
 * Optimisation de coupes 1D : tri décroissant puis first-fit. Une pièce plus longue que la barre consomme
 * des barres entières puis son reste.
 */
final class OptimiseurCoupes
{
    /**
     * @param list<int> $longueurs
     */
    public static function barres(array $longueurs, int $barreStandard): int
    {
        if ($barreStandard <= 0) {
            throw new \InvalidArgumentException('The bar length must be positive.');
        }
        rsort($longueurs);

        $barres = [];

        foreach ($longueurs as $longueur) {
            $placee = false;

            foreach ($barres as &$reste) {
                if ($longueur <= $reste) {
                    $reste -= $longueur;
                    $placee = true;
                    break;
                }
            }
            unset($reste);

            if (!$placee) {
                if ($longueur > $barreStandard) {
                    $nbBarres = intdiv($longueur, $barreStandard);
                    $resteLongueur = $longueur - ($nbBarres * $barreStandard);
                    for ($i = 0; $i < $nbBarres; ++$i) {
                        $barres[] = 0;
                    }
                    if ($resteLongueur > 0) {
                        $longueur = $resteLongueur;
                    } else {
                        continue;
                    }
                }

                $barres[] = $barreStandard - $longueur;
            }
        }

        return \count($barres);
    }
}
