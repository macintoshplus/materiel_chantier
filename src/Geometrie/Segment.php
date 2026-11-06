<?php

declare(strict_types=1);

namespace App\Geometrie;

final readonly class Segment
{
    public function __construct(public Point2D $debut, public Point2D $fin)
    {
    }

    public function longueur(): int
    {
        return $this->debut->distanceA($this->fin);
    }

    public function vecteur(): Point2D
    {
        return $this->fin->moins($this->debut);
    }

    /**
     * Distance (mm, flottant de calcul) du point au segment.
     */
    public function distanceAuPoint(Point2D $p): float
    {
        $v = $this->vecteur();
        $carre = $v->x ** 2 + $v->y ** 2;
        if (0 === $carre) {
            return hypot($p->x - $this->debut->x, $p->y - $this->debut->y);
        }
        $t = max(0.0, min(1.0, (($p->x - $this->debut->x) * $v->x + ($p->y - $this->debut->y) * $v->y) / $carre));

        return hypot($p->x - ($this->debut->x + $t * $v->x), $p->y - ($this->debut->y + $t * $v->y));
    }

    /**
     * Abscisse (mm depuis le début) de la projection du point sur le segment, bornée à [0, longueur].
     */
    public function positionDe(Point2D $p): int
    {
        $v = $this->vecteur();
        $carre = $v->x ** 2 + $v->y ** 2;
        if (0 === $carre) {
            return 0;
        }
        $t = max(0.0, min(1.0, (($p->x - $this->debut->x) * $v->x + ($p->y - $this->debut->y) * $v->y) / $carre));

        return Tolerance::arrondir($t * sqrt($carre));
    }

    /**
     * Distance signée à la droite portant le segment : positive à gauche du sens de parcours.
     */
    public function distanceSigneeALaDroite(Point2D $p): float
    {
        $v = $this->vecteur();
        $norme = hypot($v->x, $v->y);
        if (0.0 === $norme) {
            return 0.0;
        }

        return ($v->x * ($p->y - $this->debut->y) - $v->y * ($p->x - $this->debut->x)) / $norme;
    }
}
