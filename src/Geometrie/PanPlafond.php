<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Pan de plafond vu par les cloisons : plan 3D et contour dans son repère ; `projection()` donne l'emprise au sol.
 */
final readonly class PanPlafond
{
    public function __construct(public string $cle, public Plan $plan, public Polygone $contour)
    {
    }

    public function projection(): Polygone
    {
        return new Polygone(array_map(function (Point2D $p): Point2D {
            $q = $this->plan->versPiece($p);

            return new Point2D($q->x, $q->y);
        }, $this->contour->points));
    }
}
