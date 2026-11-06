<?php

declare(strict_types=1);

namespace App\Tests\Apercu;

use App\Apercu\ApercuSurface;
use App\Entity\Ouverture;
use App\Enum\TypePose;
use App\Geometrie\ContourIdentifie;
use PHPUnit\Framework\TestCase;

final class ApercuSurfaceTest extends TestCase
{
    private function contour(): ContourIdentifie
    {
        return ContourIdentifie::depuisTableau([
            ['id' => 'a', 'u' => 0, 'v' => 0],
            ['id' => 'b', 'u' => 4000, 'v' => 0],
            ['id' => 'c', 'u' => 4000, 'v' => 2500],
            ['id' => 'd', 'u' => 0, 'v' => 2500],
        ]);
    }

    public function testLAxeVerticalEstInverseEtLesSommetsSontIdentifies(): void
    {
        $vue = (new ApercuSurface())->construire($this->contour(), []);

        self::assertSame('0,0 4000,0 4000,-2500 0,-2500', $vue['contour']);
        self::assertSame(['a', 'b', 'c', 'd'], array_column($vue['sommets'], 'id'));
        self::assertSame([], $vue['ouvertures']);
    }

    public function testUneOuvertureEnTunnelDessineSesRetoursEtUneEnAppliqueNon(): void
    {
        $fenetre = new Ouverture();
        $fenetre->setType('fenetre');
        $fenetre->definirRectangle(500, 900, 1000, 1200);
        $fenetre->setTypePose(TypePose::Tunnel);
        $fenetre->setRefoncement(100);
        $porte = new Ouverture();
        $porte->setType('porte');
        $porte->definirRectangle(2000, 0, 900, 2000);
        $porte->setTypePose(TypePose::Applique);

        $vue = (new ApercuSurface())->construire($this->contour(), [
            ['ouverture' => $fenetre, 'forme' => $fenetre->getForme()],
            ['ouverture' => $porte, 'forme' => $porte->getForme()],
        ]);

        [$f, $p] = $vue['ouvertures'];
        self::assertTrue($f['tunnel']);
        self::assertCount(4, $f['retours']);
        self::assertSame(-2100, $f['y']);
        self::assertFalse($p['tunnel']);
        self::assertSame([], $p['retours']);
        self::assertTrue($p['porte']);
    }

    public function testUnContourVideDonneUneVueParDefaut(): void
    {
        $vue = (new ApercuSurface())->construire(new ContourIdentifie([]), []);

        self::assertNotSame('', $vue['viewBox']);
        self::assertSame('', $vue['contour']);
    }
}
