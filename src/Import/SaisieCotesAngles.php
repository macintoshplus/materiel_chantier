<?php

declare(strict_types=1);

namespace App\Import;

use App\Geometrie\Avertissement;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;

/**
 * Construit un contour à partir de côtés (mm) et d'angles intérieurs (degrés) relevés en faisant le tour de la pièce
 * dans le sens antihoraire. L'angle i est celui mesuré à la fin du côté i ; le dernier angle (retour au point de
 * départ) n'est pas nécessaire au tracé. L'écart de fermeture est réparti proportionnellement à la longueur cumulée
 * (compensation de Bowditch) s'il reste sous le seuil.
 */
final class SaisieCotesAngles
{
    public const SEUIL_FERMETURE_MM = 20.0;

    /**
     * @param list<float> $cotes
     * @param list<float> $angles angle intérieur à la fin de chaque côté (90 si absent)
     *
     * @throws ErreurGeometrique
     */
    public function tracer(array $cotes, array $angles, float $seuil = self::SEUIL_FERMETURE_MM): ResultatCotes
    {
        $n = \count($cotes);
        foreach ($cotes as $i => $cote) {
            $angle = $angles[$i] ?? 90.0;
            if (!is_finite($cote) || $cote <= 0.0 || !is_finite($angle) || $angle < 1.0 || $angle > 359.0) {
                throw new ErreurGeometrique('import_side_invalid', ['number' => $i + 1]);
            }
        }
        if (2 === $n && abs(($angles[0] ?? 90.0) - 90.0) < 1e-9) {
            $w = (int) round($cotes[0]);
            $h = (int) round($cotes[1]);
            $rect = [new Point2D(0, 0), new Point2D($w, 0), new Point2D($w, $h), new Point2D(0, $h)];

            return new ResultatCotes($rect, 0.0, $rect);
        }
        if ($n < 3) {
            throw new ErreurGeometrique('polygon_not_simple');
        }

        $brut = [[0.0, 0.0]];
        $cap = 0.0;
        $x = 0.0;
        $y = 0.0;
        foreach ($cotes as $i => $cote) {
            $x += $cote * cos($cap);
            $y += $cote * sin($cap);
            $brut[] = [$x, $y];
            $cap += deg2rad(180.0 - ($angles[$i] ?? 90.0));
        }
        $trace = array_map(static fn (array $p): Point2D => new Point2D((int) round($p[0]), (int) round($p[1])), $brut);
        $ecart = hypot($x, $y);
        if ($ecart > $seuil) {
            return new ResultatCotes($trace, $ecart, null, new Avertissement('import_closure_gap', ['gap' => round($ecart, 1), 'threshold' => $seuil]));
        }

        $total = array_sum($cotes);
        $cumul = 0.0;
        $points = [];
        foreach ($brut as $i => [$px, $py]) {
            if ($i >= $n) {
                break;
            }
            $part = $total > 0.0 ? $cumul / $total : 0.0;
            $points[] = new Point2D((int) round($px - $x * $part), (int) round($py - $y * $part));
            $cumul += $cotes[$i];
        }
        $polygone = new Polygone($points);
        if (!$polygone->estSimple()) {
            throw new ErreurGeometrique('polygon_not_simple');
        }
        if (!$polygone->sensAntiHoraire()) {
            $points = array_reverse($points);
        }

        return new ResultatCotes(
            $trace,
            $ecart,
            $points,
            $ecart > 0.5 ? new Avertissement('import_closure_adjusted', ['gap' => round($ecart, 1)]) : null,
        );
    }
}
