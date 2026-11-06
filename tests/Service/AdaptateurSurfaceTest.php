<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Ouverture;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Enum\TypePose;
use App\Enum\TypeSurface;
use App\Geometrie\ContourIdentifie;
use App\Service\AdaptateurSurface;
use App\Service\OssatureCloisonCalculator;
use App\Service\OssatureDoublageCalculator;
use App\Tests\Service\Caracterisation\ScenariosTrait;
use PHPUnit\Framework\TestCase;

/**
 * Adaptateur Surface vers calculateurs historiques : le `Mur` produit est celui des scénarios de caractérisation
 * de la phase 0, donc les résultats des calculateurs sont identiques.
 */
final class AdaptateurSurfaceTest extends TestCase
{
    use ScenariosTrait;

    private function surface(int $largeur, int $hauteur): Surface
    {
        $projet = new Projet();
        $projet->setNom('P');
        $surface = new Surface(new Piece($projet), TypeSurface::Mur);
        $surface->definirContour(ContourIdentifie::depuisTableau([
            ['id' => 'a', 'u' => 0, 'v' => 0],
            ['id' => 'b', 'u' => $largeur, 'v' => 0],
            ['id' => 'c', 'u' => $largeur, 'v' => $hauteur],
            ['id' => 'd', 'u' => 0, 'v' => $hauteur],
        ]));

        return $surface;
    }

    private function ouverture(Surface $surface, string $type, int $u, int $v, int $largeur, int $hauteur): void
    {
        $o = new Ouverture($surface);
        $o->setType($type);
        $o->definirRectangle($u, $v, $largeur, $hauteur);
        $o->setTypePose(TypePose::Tunnel);
        $o->setTypeContour('plaque-platre');
        $o->setRefoncement(500);
    }

    private function murAvecOuverturesAdapte(): \App\Model\Mur
    {
        $surface = $this->surface(7000, 2500);
        $this->ouverture($surface, 'fenetre', 1000, 800, 1000, 1200);
        $this->ouverture($surface, 'porte', 3000, 0, 920, 2050);

        return (new AdaptateurSurface())->versMur($surface);
    }

    public function test_un_mur_plein_donne_le_scenario_de_caracterisation(): void
    {
        $mur = (new AdaptateurSurface())->versMur($this->surface(3000, 2500));
        $mur->setEcartDebut(0);
        $mur->setEcartFin(0);

        self::assertEquals($this->murPlein(), $mur);
        $r = (new OssatureDoublageCalculator(new OssatureCloisonCalculator()))->calculer($mur, $this->montant(), $this->plaque());
        self::assertCount(1, $r['rail_appuis']);
        self::assertCount(7, $r['appuis']);
    }

    public function test_un_mur_avec_ouvertures_donne_le_scenario_de_l_ecran_actuel(): void
    {
        $mur = $this->murAvecOuverturesAdapte();

        self::assertEquals($this->murAvecOuvertures(), $mur);

        $doublage = (new OssatureDoublageCalculator(new OssatureCloisonCalculator()))->calculer($mur, $this->montant(), $this->plaque());
        self::assertCount(19, $doublage['montants']);
        self::assertCount(5, $doublage['rails']);
        self::assertCount(13, $doublage['appuis']);
        self::assertCount(3, $doublage['rail_appuis']);

        self::assertEquals(
            (new OssatureCloisonCalculator())->calculer($this->murAvecOuvertures(), $this->montant(), $this->plaque()),
            (new OssatureCloisonCalculator())->calculer($mur, $this->montant(), $this->plaque()),
        );
    }

    public function test_une_surface_non_rectangulaire_est_refusee(): void
    {
        $surface = $this->surface(3000, 2500);
        $surface->definirContour(ContourIdentifie::depuisTableau([
            ['id' => 'a', 'u' => 0, 'v' => 0],
            ['id' => 'b', 'u' => 3000, 'v' => 0],
            ['id' => 'c', 'u' => 1500, 'v' => 2500],
        ]));

        $this->expectException(\InvalidArgumentException::class);
        (new AdaptateurSurface())->versMur($surface);
    }

    public function test_la_surface_calculable_porte_la_geometrie_et_les_ouvertures(): void
    {
        $surface = $this->surface(7000, 2500);
        $this->ouverture($surface, 'porte', 3000, 0, 920, 2050);

        $c = (new AdaptateurSurface())->versCalculable($surface);

        self::assertSame(7000 * 2500, $c->surface());
        self::assertSame(920 * 2050, $c->surfaceOuvertures());
        self::assertCount(1, $c->ouvertures);
    }
}
