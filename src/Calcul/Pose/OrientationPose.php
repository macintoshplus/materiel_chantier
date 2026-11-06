<?php

declare(strict_types=1);

namespace App\Calcul\Pose;

use App\Catalogue\Vue\Orientation;
use App\Geometrie\Direction;
use App\Geometrie\Plan;

/**
 * Orientation de pose d'une ossature, déduite de la direction de l'ossature dans le repère de la surface :
 * verticale si l'élément est vertical dans l'espace, horizontale s'il est horizontal. Entre les deux
 * (pan incliné), l'orientation est dite intermédiaire : le résolveur retient la plus défavorable.
 */
final readonly class OrientationPose
{
    private const float TOLERANCE = 0.02;

    public function __construct(public Orientation $orientation, public bool $intermediaire = false)
    {
    }

    public static function deduire(Plan $plan, Direction $direction): self
    {
        $axe = Direction::Verticale === $direction ? $plan->v : $plan->u;
        $norme = sqrt($axe->x ** 2 + $axe->y ** 2 + $axe->z ** 2);
        $verticalite = 0.0 === $norme ? 1.0 : abs($axe->z) / $norme;
        if ($verticalite >= 1.0 - self::TOLERANCE) {
            return new self(Orientation::Verticale);
        }
        if ($verticalite <= self::TOLERANCE) {
            return new self(Orientation::Horizontale);
        }

        return new self($verticalite >= 0.5 ? Orientation::Verticale : Orientation::Horizontale, true);
    }
}
