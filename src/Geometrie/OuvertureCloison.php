<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Une ouverture de cloison est saisie dans le repère du tracé (`u` depuis le début) et projetée sur les deux
 * faces : la face B est dans le sens du tracé, la face A est inversée (`u` depuis la fin).
 */
final class OuvertureCloison
{
    public function projeter(Polygone $dansLeTrace, int $longueurCloison): ProjectionOuverture
    {
        $miroir = array_reverse(array_map(
            static fn (Point2D $p): Point2D => new Point2D($longueurCloison - $p->x, $p->y),
            $dansLeTrace->points,
        ));

        return new ProjectionOuverture(new Polygone($miroir), $dansLeTrace);
    }
}
