<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

/**
 * Section de conducteur : décimale en texte ("1.5") dans les données, entier en centièmes de mm² dans le calcul.
 */
final class Section
{
    public static function centiemes(string|int $section): int
    {
        $texte = str_replace(',', '.', trim((string) $section));
        if (1 !== preg_match('/^(\d+)(?:\.(\d{1,2}))?\d*$/', $texte, $m)) {
            throw new \InvalidArgumentException(\sprintf('Invalid conductor section "%s".', $texte));
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    public static function texte(int $centiemes): string
    {
        $entier = intdiv($centiemes, 100);
        $reste = $centiemes % 100;
        if (0 === $reste) {
            return (string) $entier;
        }

        return $entier.'.'.rtrim(str_pad((string) $reste, 2, '0', \STR_PAD_LEFT), '0');
    }
}
