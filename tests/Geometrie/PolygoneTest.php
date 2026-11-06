<?php

declare(strict_types=1);

namespace App\Tests\Geometrie;

use App\Geometrie\Point2D;
use App\Geometrie\Polygone;
use PHPUnit\Framework\TestCase;

final class PolygoneTest extends TestCase
{
    /** @param list<array{int, int}> $pts */
    private static function poly(array $pts): Polygone
    {
        return new Polygone(array_map(static fn (array $p): Point2D => new Point2D($p[0], $p[1]), $pts));
    }

    private static function l(): Polygone
    {
        return self::poly([[0, 0], [4000, 0], [4000, 2000], [2000, 2000], [2000, 3000], [0, 3000]]);
    }

    public function testRectangleAirePerimetreEtSens(): void
    {
        $r = self::poly([[0, 0], [4000, 0], [4000, 3000], [0, 3000]]);
        self::assertSame(12_000_000, $r->aire());
        self::assertSame(14_000, $r->perimetre());
        self::assertTrue($r->sensAntiHoraire());
        self::assertFalse(self::poly([[0, 0], [0, 3000], [4000, 3000], [4000, 0]])->sensAntiHoraire());
        self::assertTrue($r->estSimple());
        $b = $r->boiteEnglobante();
        self::assertSame([0, 0, 4000, 3000], [$b->minX, $b->minY, $b->maxX, $b->maxY]);
    }

    public function testFormeEnL(): void
    {
        $l = self::l();
        self::assertSame(10_000_000, $l->aire());
        self::assertTrue($l->estSimple());
        self::assertTrue($l->contient(new Point2D(1000, 2500)));
        self::assertFalse($l->contient(new Point2D(3000, 2500)));
        self::assertTrue($l->contient(new Point2D(4000, 1000)), 'bord inclus');
        self::assertFalse($l->contient(new Point2D(4000, 1000), false));
    }

    public function testContourQuiSeCroiseEstRefuse(): void
    {
        self::assertFalse(self::poly([[0, 0], [4000, 3000], [4000, 0], [0, 3000]])->estSimple());
        self::assertFalse(self::poly([[0, 0], [1000, 0]])->estSimple());
        self::assertFalse(self::poly([[0, 0], [1000, 0], [2000, 0]])->estSimple(), 'aire nulle');
        // un sommet qui touche une arête non adjacente
        self::assertFalse(self::poly([[0, 0], [4000, 0], [4000, 3000], [2000, 0], [0, 3000]])->estSimple());
    }

    public function testContientPolygoneEtIntersecte(): void
    {
        $l = self::l();
        self::assertTrue($l->contientPolygone(self::poly([[100, 100], [900, 100], [900, 900], [100, 900]])));
        self::assertFalse($l->contientPolygone(self::poly([[1500, 1500], [3000, 1500], [3000, 2500], [1500, 2500]])), 'sort par l\'angle rentrant');
        // aucun sommet de l'un dans l'autre mais croisement
        $a = self::poly([[0, 1000], [3000, 1000], [3000, 1500], [0, 1500]]);
        $b = self::poly([[1000, 0], [1500, 0], [1500, 3000], [1000, 3000]]);
        self::assertTrue($a->intersecte($b));
        self::assertTrue($a->chevauche($b));
        $loin = self::poly([[5000, 0], [6000, 0], [6000, 1000], [5000, 1000]]);
        self::assertFalse($a->intersecte($loin));
        // contact de bords : intersecte oui, chevauche non
        $contact = self::poly([[3000, 1000], [4000, 1000], [4000, 1500], [3000, 1500]]);
        self::assertTrue($a->intersecte($contact));
        self::assertFalse($a->chevauche($contact));
    }
}
