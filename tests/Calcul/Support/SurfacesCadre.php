<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Support;

use App\Calcul\Surface\OuvertureCalculable;
use App\Calcul\Surface\SurfaceCalculable;
use App\Enum\TypePose;
use App\Enum\TypeSurface;
use App\Geometrie\ContourIdentifie;
use App\Geometrie\Plan;
use App\Geometrie\Point2D;
use App\Geometrie\Point3D;
use App\Geometrie\Polygone;

/**
 * Surfaces verticales de test (repère `(u, v)` en mm) : rectangle, ou rectangle dont le haut suit un plafond en pente.
 */
trait SurfacesCadre
{
    /**
     * @param list<OuvertureCalculable> $ouvertures
     */
    private function mur(int $largeur, int $hauteurGauche, ?int $hauteurDroite = null, array $ouvertures = [], TypeSurface $type = TypeSurface::CloisonFace): SurfaceCalculable
    {
        return new SurfaceCalculable(
            $type,
            new Plan(new Point3D(0, 0, 0), new Point3D(1000, 0, 0), new Point3D(0, 0, 1000)),
            ContourIdentifie::depuisTableau([
                ['id' => 'a', 'u' => 0, 'v' => 0],
                ['id' => 'b', 'u' => $largeur, 'v' => 0],
                ['id' => 'c', 'u' => $largeur, 'v' => $hauteurDroite ?? $hauteurGauche],
                ['id' => 'd', 'u' => 0, 'v' => $hauteurGauche],
            ]),
            $ouvertures,
        );
    }

    private function ouverture(int $u, int $v, int $largeur, int $hauteur, string $type = 'porte', TypePose $pose = TypePose::Tunnel): OuvertureCalculable
    {
        return new OuvertureCalculable(
            new Polygone([new Point2D($u, $v), new Point2D($u + $largeur, $v), new Point2D($u + $largeur, $v + $hauteur), new Point2D($u, $v + $hauteur)]),
            $type,
            $pose,
        );
    }
}
