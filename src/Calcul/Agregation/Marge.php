<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

/**
 * Arithmétique de la marge de sécurité en entiers : un pourcentage est exprimé en centièmes de pourcent
 * (10 % = 1000), jamais en flottant, pour que `ceil(11 × 1,10) = 13` soit exact.
 */
final class Marge
{
    public const int ECHELLE = 10000;
    public const int MAX_CENTIEMES = 10000;

    /**
     * Centièmes de pourcent d'une valeur saisie (`'10'`, `'12,5'`, `'7.25'`, `10`, `12.5`) ; `null` si la valeur n'est
     * pas un pourcentage de 0 à 100 avec au plus deux décimales.
     */
    public static function centiemes(mixed $valeur): ?int
    {
        if (\is_int($valeur)) {
            $texte = (string) $valeur;
        } elseif (\is_float($valeur)) {
            $texte = number_format($valeur, 2, '.', '');
        } elseif (\is_string($valeur)) {
            $texte = trim($valeur);
        } else {
            return null;
        }
        if (1 !== preg_match('/^(\d{1,3})(?:[.,](\d{1,2}))?$/', $texte, $m)) {
            return null;
        }
        $centiemes = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');

        return $centiemes <= self::MAX_CENTIEMES ? $centiemes : null;
    }

    /**
     * `ceil(besoin × (1 + marge) / unitesParLot)` en un seul arrondi.
     */
    public static function lots(int $besoin, int $margeCentiemes, int $unitesParLot): int
    {
        if ($besoin <= 0) {
            return 0;
        }
        $diviseur = self::ECHELLE * max(1, $unitesParLot);

        return intdiv($besoin * (self::ECHELLE + $margeCentiemes) + $diviseur - 1, $diviseur);
    }

    /**
     * Quantité avec marge en millièmes d'unité, arrondie au supérieur (12,1 plaques = 12100).
     */
    public static function quantiteMilli(int $besoin, int $margeCentiemes): int
    {
        return intdiv(max(0, $besoin) * (self::ECHELLE + $margeCentiemes) + 9, 10);
    }
}
