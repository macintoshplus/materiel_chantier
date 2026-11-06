<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Conversion texte <-> sommets du polygone au sol (mm entiers, anti-horaire).
 * Format texte : un sommet par ligne, « x;y » en mètres (virgule ou point décimal).
 */
final class FormeSol
{
    /**
     * @return list<array{x: int, y: int}>
     *
     * @throws \InvalidArgumentException message = clé de traduction (domaine validators)
     */
    public static function depuisTexte(string $texte): array
    {
        $points = [];
        foreach (preg_split('/\R/', trim($texte)) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ('' === $ligne) {
                continue;
            }
            if (1 !== preg_match('/^(-?\d+(?:[.,]\d+)?)\s*[;\s]\s*(-?\d+(?:[.,]\d+)?)$/', $ligne, $m)) {
                throw new \InvalidArgumentException('Each line of the floor outline must look like "x;y" (metres).');
            }
            $points[] = ['x' => self::enMm($m[1]), 'y' => self::enMm($m[2])];
        }
        if ([] === $points) {
            return [];
        }
        if (\count($points) < 3) {
            throw new \InvalidArgumentException('The floor outline needs at least 3 vertices.');
        }
        $n = \count($points);
        for ($i = 0; $i < $n; ++$i) {
            if ($points[$i] === $points[($i + 1) % $n]) {
                throw new \InvalidArgumentException('Two consecutive vertices of the floor outline are identical.');
            }
        }
        $aire = self::aireSignee($points);
        if (0 === $aire) {
            throw new \InvalidArgumentException('The floor outline has no area.');
        }

        $points = $aire < 0 ? array_reverse($points) : $points;
        if (!Polygone::depuisTableaux($points)->estSimple()) {
            throw new \InvalidArgumentException('The floor outline crosses itself.');
        }

        return $points;
    }

    /**
     * @param list<array{x: int, y: int}> $points
     */
    public static function versTexte(array $points): string
    {
        return implode("\n", array_map(
            static fn (array $p): string => self::enMetres($p['x']).';'.self::enMetres($p['y']),
            $points,
        ));
    }

    /**
     * Deux fois l'aire (mm²), positive si le sens est anti-horaire.
     *
     * @param list<array{x: int, y: int}> $points
     */
    public static function aireSignee(array $points): int
    {
        $somme = 0;
        $n = \count($points);
        for ($i = 0; $i < $n; ++$i) {
            $a = $points[$i];
            $b = $points[($i + 1) % $n];
            $somme += $a['x'] * $b['y'] - $b['x'] * $a['y'];
        }

        return $somme;
    }

    private static function enMm(string $metres): int
    {
        return (int) round((float) str_replace(',', '.', $metres) * 1000);
    }

    private static function enMetres(int $mm): string
    {
        return rtrim(rtrim(number_format($mm / 1000, 3, '.', ''), '0'), '.');
    }
}
