<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Finition;

use App\Catalogue\MaterielLisible;

/**
 * Règles de quantité des consommables de finition (entiers : mm, grammes, pourcents).
 */
final class Consommables
{
    /**
     * Rouleaux de bande : `ceil(longueur × (1 + marge) / longueur_rouleau)`.
     */
    public static function rouleaux(int $longueurMm, int $margePct, int $longueurRouleauMm): int
    {
        if ($longueurRouleauMm <= 0) {
            throw new \InvalidArgumentException('The roll length must be positive.');
        }

        return (int) ceil($longueurMm * (100 + max(0, $margePct)) / (100 * $longueurRouleauMm));
    }

    /**
     * Nombre de points de fixation le long d'un profilé : `ceil(longueur / pas)`.
     */
    public static function fixations(int $longueurMm, int $pasMm): int
    {
        if ($pasMm <= 0) {
            throw new \InvalidArgumentException('The fixing pitch must be positive.');
        }

        return (int) ceil($longueurMm / $pasMm);
    }

    /**
     * Masse d'enduit (kg, arrondie au kg supérieur) pour une surface (mm²) et une consommation en g/m².
     */
    public static function kilos(int $surfaceMm2, int $grammesParM2): int
    {
        return (int) ceil($surfaceMm2 * $grammesParM2 / 1_000_000_000);
    }

    /**
     * Valeur décimale du catalogue en entier (millièmes) : 0,35 donne 350.
     */
    public static function milliemes(MaterielLisible $article, string $chemin): ?int
    {
        $v = $article->valeur($chemin);

        return \is_int($v) || \is_float($v) ? (int) round($v * 1000) : null;
    }
}
