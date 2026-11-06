<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Pose;

use App\Calcul\Pose\OrientationPose;
use App\Catalogue\Vue\Orientation;
use App\Geometrie\Direction;
use App\Geometrie\Plan;
use App\Geometrie\Point3D;
use PHPUnit\Framework\TestCase;

final class OrientationPoseTest extends TestCase
{
    public function test_un_mur_est_vertical_et_un_plafond_horizontal(): void
    {
        $mur = new Plan(new Point3D(0, 0, 0), new Point3D(1000, 0, 0), new Point3D(0, 0, 1000));
        $plafond = new Plan(new Point3D(0, 0, 2500), new Point3D(1000, 0, 0), new Point3D(0, 1000, 0));

        $verticale = OrientationPose::deduire($mur, Direction::Verticale);
        $horizontale = OrientationPose::deduire($plafond, Direction::Verticale);
        $surMur = OrientationPose::deduire($mur, Direction::Horizontale);

        self::assertSame([Orientation::Verticale, false], [$verticale->orientation, $verticale->intermediaire]);
        self::assertSame([Orientation::Horizontale, false], [$horizontale->orientation, $horizontale->intermediaire]);
        self::assertSame([Orientation::Horizontale, false], [$surMur->orientation, $surMur->intermediaire]);
    }

    public function test_un_pan_incline_est_intermediaire(): void
    {
        $pan = new Plan(new Point3D(0, 0, 2500), new Point3D(1000, 0, 0), new Point3D(0, 866, 500));

        $o = OrientationPose::deduire($pan, Direction::Verticale);

        self::assertTrue($o->intermediaire);
    }
}
