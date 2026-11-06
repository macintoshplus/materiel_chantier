<?php

declare(strict_types=1);

namespace App\Import;

use App\Geometrie\ErreurGeometrique;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;

/**
 * Décalage vers l'intérieur d'un contour antihoraire (angles vifs conservés) : sert à passer d'un tracé en axe ou en
 * nu extérieur des murs au nu intérieur.
 */
final class DecalageContour
{
    /**
     * @param list<Point2D> $points contour antihoraire
     *
     * @return list<Point2D>
     *
     * @throws ErreurGeometrique `import_offset_invalid` si le résultat se croise, s'inverse ou s'effondre
     */
    public function vers(array $points, int $distance): array
    {
        if (0 === $distance) {
            return $points;
        }
        $n = \count($points);
        // Droites décalées : point + vecteur directeur, pour chaque arête.
        $droites = [];
        for ($i = 0; $i < $n; ++$i) {
            $a = $points[$i];
            $b = $points[($i + 1) % $n];
            $longueur = hypot($b->x - $a->x, $b->y - $a->y);
            if ($longueur < 1e-9) {
                throw new ErreurGeometrique('import_offset_invalid');
            }
            $dx = ($b->x - $a->x) / $longueur;
            $dy = ($b->y - $a->y) / $longueur;
            // Normale gauche (intérieur d'un contour antihoraire).
            $droites[] = [$a->x - $dy * $distance, $a->y + $dx * $distance, $dx, $dy];
        }
        $res = [];
        for ($i = 0; $i < $n; ++$i) {
            [$px, $py, $dx, $dy] = $droites[($i + $n - 1) % $n];
            [$qx, $qy, $ex, $ey] = $droites[$i];
            $det = $dx * $ey - $dy * $ex;
            if (abs($det) < 1e-9) {
                $res[] = new Point2D((int) round($qx), (int) round($qy));
                continue;
            }
            $t = (($qx - $px) * $ey - ($qy - $py) * $ex) / $det;
            $res[] = new Point2D((int) round($px + $t * $dx), (int) round($py + $t * $dy));
        }

        $polygone = new Polygone($res);
        if (!$polygone->estSimple() || !$polygone->sensAntiHoraire() || $polygone->aireDoublee() <= 0) {
            throw new ErreurGeometrique('import_offset_invalid');
        }
        // Chaque arête doit garder son sens (sinon un côté s'est retourné).
        for ($i = 0; $i < $n; ++$i) {
            $avant = $points[($i + 1) % $n]->moins($points[$i]);
            $apres = $res[($i + 1) % $n]->moins($res[$i]);
            if ($avant->x * $apres->x + $avant->y * $apres->y <= 0) {
                throw new ErreurGeometrique('import_offset_invalid');
            }
        }

        return $res;
    }
}
