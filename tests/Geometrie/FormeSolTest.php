<?php

declare(strict_types=1);

namespace App\Tests\Geometrie;

use App\Geometrie\FormeSol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormeSolTest extends TestCase
{
    public function testLesMetresDeviennentDesMillimetresEntiers(): void
    {
        $points = FormeSol::depuisTexte("0;0\n4,5;0\n4.5;3\n0;3");

        self::assertSame([['x' => 0, 'y' => 0], ['x' => 4500, 'y' => 0], ['x' => 4500, 'y' => 3000], ['x' => 0, 'y' => 3000]], $points);
    }

    public function testUnSensHoraireEstInverseEnAntiHoraire(): void
    {
        $points = FormeSol::depuisTexte("0;0\n0;3\n4;3\n4;0");

        self::assertGreaterThan(0, FormeSol::aireSignee($points));
        self::assertSame(['x' => 4000, 'y' => 0], $points[0]);
    }

    public function testUnTexteVideDonneAucunContour(): void
    {
        self::assertSame([], FormeSol::depuisTexte("  \n"));
    }

    #[DataProvider('contoursInvalides')]
    public function testUnContourInvalideEstRefuse(string $texte): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FormeSol::depuisTexte($texte);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contoursInvalides(): iterable
    {
        yield 'ligne illisible' => ["0;0\nabc\n1;1"];
        yield 'deux sommets' => ["0;0\n1;1"];
        yield 'sommets confondus' => ["0;0\n0;0\n1;1"];
        yield 'surface nulle' => ["0;0\n1;1\n2;2"];
        yield 'contour qui se croise' => ["0;0\n4;3\n4;0\n0;3"];
    }

    public function testAllerRetourTexte(): void
    {
        $points = [['x' => 0, 'y' => 0], ['x' => 4500, 'y' => 0], ['x' => 4500, 'y' => 3250]];

        self::assertSame($points, FormeSol::depuisTexte(FormeSol::versTexte($points)));
    }
}
