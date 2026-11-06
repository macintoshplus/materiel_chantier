<?php

declare(strict_types=1);

namespace App\Chiffrage;

/**
 * Répartition d'un entier au prorata de poids, sans perte : la somme des parts est exactement le total
 * (méthode du plus fort reste ; à égalité, la première part l'emporte).
 */
final class Repartition
{
    /**
     * @param list<int> $poids
     *
     * @return list<int>
     */
    public static function proportionnelle(int $total, array $poids): array
    {
        $somme = array_sum($poids);
        if ([] === $poids || $somme <= 0 || $total <= 0) {
            return array_fill(0, \count($poids), 0);
        }
        $parts = [];
        $restes = [];
        $distribue = 0;
        foreach ($poids as $i => $poidsI) {
            $parts[$i] = intdiv($total * $poidsI, $somme);
            $restes[$i] = ($total * $poidsI) % $somme;
            $distribue += $parts[$i];
        }
        $ordre = array_keys($restes);
        usort($ordre, static fn (int $a, int $b): int => [$restes[$b], $a] <=> [$restes[$a], $b]);
        for ($k = 0; $k < $total - $distribue; ++$k) {
            ++$parts[$ordre[$k]];
        }

        return array_values($parts);
    }
}
