<?php

declare(strict_types=1);

namespace App\Chiffrage;

/**
 * Montants en centimes d'euro (entiers) : les prix décimaux du catalogue (`'12.50'`) sont convertis sans flottant.
 */
final class Montant
{
    /**
     * Centimes d'un montant décimal (`'12.5'`, `'12,50'`, `'3'`), arrondi au centime inférieur au-delà de deux décimales.
     */
    public static function centimes(?string $decimal): int
    {
        if (null === $decimal || 1 !== preg_match('/^\s*(\d+)(?:[.,](\d+))?\s*$/', $decimal, $m)) {
            return 0;
        }

        return (int) $m[1] * 100 + (int) str_pad(substr($m[2] ?? '', 0, 2), 2, '0');
    }
}
