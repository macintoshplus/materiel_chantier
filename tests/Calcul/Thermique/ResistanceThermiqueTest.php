<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Thermique;

use App\Calcul\Thermique\ResistanceThermique;
use App\Enum\TypeSurface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResistanceThermiqueTest extends TestCase
{
    /**
     * @return iterable<string, array{TypeSurface, int, int, int, int}>
     */
    public static function cas(): iterable
    {
        yield 'mur' => [TypeSurface::Mur, 500, 2000, 2670, 375];
        yield 'plafond' => [TypeSurface::PlafondPan, 0, 3000, 3140, 318];
        yield 'sol' => [TypeSurface::Sol, 0, 1000, 1210, 826];
    }

    #[DataProvider('cas')]
    public function test_resistance_totale_et_coefficient_u_entiers(TypeSurface $type, int $paroi, int $systeme, int $rt, int $u): void
    {
        self::assertSame($rt, ResistanceThermique::totale($type, $paroi, $systeme));
        self::assertSame($u, ResistanceThermique::coefficientU($rt));
    }

    public function test_u_refuse_une_resistance_nulle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ResistanceThermique::coefficientU(0);
    }
}
