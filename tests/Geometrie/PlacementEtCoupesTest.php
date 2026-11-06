<?php

declare(strict_types=1);

namespace App\Tests\Geometrie;

use App\Geometrie\ContourIdentifie;
use App\Geometrie\Direction;
use App\Geometrie\OuvertureAPlacer;
use App\Geometrie\Plan;
use App\Geometrie\PlacementSurSurface;
use App\Geometrie\Point2D;
use App\Geometrie\Point3D;
use App\Geometrie\Rectangle;
use App\Geometrie\Sommet;
use App\Geometrie\SurfaceGeometrique;
use PHPUnit\Framework\TestCase;

final class PlacementEtCoupesTest extends TestCase
{
    private static function mur(): SurfaceGeometrique
    {
        return new SurfaceGeometrique(
            new Plan(new Point3D(0, 0, 0), new Point3D(1000, 0, 0), new Point3D(0, 0, 1000)),
            new ContourIdentifie([
                new Sommet('a', new Point2D(0, 0)), new Sommet('b', new Point2D(4000, 0)),
                new Sommet('c', new Point2D(4000, 2500)), new Sommet('d', new Point2D(0, 2500)),
            ]),
        );
    }

    private static function ouverture(string $nom, int $u, int $v, int $l, int $h): OuvertureAPlacer
    {
        return new OuvertureAPlacer($nom, (new Rectangle($u, $v, $l, $h))->polygone());
    }

    /**
     * @param list<OuvertureAPlacer> $o
     *
     * @return list<string>
     */
    private static function codes(array $o, int $min = 0): array
    {
        return array_map(static fn ($a): string => $a->code, (new PlacementSurSurface())->verifier(self::mur()->polygone(), $o, $min));
    }

    public function testPlacementValide(): void
    {
        self::assertSame([], self::codes([self::ouverture('Door', 500, 0, 900, 2100), self::ouverture('Window', 2000, 900, 1200, 1200)]));
    }

    public function testOuvertureHorsDeLaSurface(): void
    {
        self::assertSame(['opening_outside_surface'], self::codes([self::ouverture('Window', 3500, 900, 1200, 1200)]));
        self::assertSame(['opening_outside_surface'], self::codes([self::ouverture('Window', 500, 2000, 800, 800)]));
        self::assertSame(['opening_outside_surface'], self::codes([self::ouverture('Window', -10, 900, 800, 800)]));
    }

    public function testChevauchementEtContactDeBords(): void
    {
        self::assertSame(['opening_overlap'], self::codes([self::ouverture('A', 500, 900, 1000, 1000), self::ouverture('B', 1200, 900, 1000, 1000)]));
        self::assertSame([], self::codes([self::ouverture('A', 500, 900, 1000, 1000), self::ouverture('B', 1500, 900, 1000, 1000)]), 'simple contact');
    }

    public function testDistanceMinimaleAUneArete(): void
    {
        $fenetre = self::ouverture('Window', 50, 900, 1000, 1000);
        self::assertSame([], self::codes([$fenetre]));
        self::assertSame(['opening_too_close_to_edge'], self::codes([$fenetre], 100));
        // la porte repose sur le sol : l'arête basse est ignorée
        self::assertSame([], self::codes([self::ouverture('Door', 500, 0, 900, 2100)], 100));
    }

    public function testCoupesSurUnMurRectangulaire(): void
    {
        $coupes = self::mur()->coupes(Direction::Verticale, 600);

        self::assertCount(7, $coupes); // 0, 600 ... 3600 (4000 non atteint)
        foreach ($coupes as $c) {
            self::assertSame(2500, $c->longueur());
        }
        $horizontales = self::mur()->coupes(Direction::Horizontale, 1000);
        self::assertCount(3, $horizontales);
        self::assertSame(4000, $horizontales[0]->longueur());
    }

    public function testCoupesSoustraientLesOuvertures(): void
    {
        $mur = self::mur()->avecOuvertures([(new Rectangle(1000, 0, 900, 2100))->polygone(), (new Rectangle(2000, 900, 1000, 1000))->polygone()]);
        $parX = [];
        foreach ($mur->coupes(Direction::Verticale, 500) as $c) {
            $parX[$c->debut->x][] = $c->longueur();
        }

        self::assertSame([2500], $parX[0]);
        self::assertSame([2500], $parX[500]);
        self::assertSame([400], $parX[1500], 'sous la porte, au-dessus : 400 mm');
        self::assertSame([900, 600], $parX[2500], 'sous et au-dessus de la fenêtre');
        self::assertSame([2500], $parX[3000], 'bord de la fenêtre : non coupé');
    }
}
