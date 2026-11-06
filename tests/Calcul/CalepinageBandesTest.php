<?php

declare(strict_types=1);

namespace App\Tests\Calcul;

use App\Calcul\Cadre\BandeRetour;
use App\Calcul\Calepinage\CalepinageBandes;
use App\Calcul\Couche\Finition\Consommables;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalepinageBandesTest extends TestCase
{
    /**
     * @return iterable<string, array{list<BandeRetour>, int}>
     */
    public static function cas(): iterable
    {
        yield 'aucune bande' => [[], 0];
        // Plaque 1200 x 2500 : 14 rangées de 83 mm ; 1000 + 2 x 2100 = 3 bandes à ranger dans des barres de 2500.
        yield 'porte' => [[new BandeRetour('linteau', 1000, 83), new BandeRetour('cote', 2100, 83), new BandeRetour('cote', 2100, 83)], 1];
        // 40 bandes de 2400 mm : 40 rangées, 14 par plaque.
        yield 'beaucoup de bandes' => [array_fill(0, 40, new BandeRetour('cote', 2400, 83)), 3];
        // Bande plus large que la plaque : comptée à la surface (2 x 1500 x 1300 = 3,9 m2 -> 2 plaques de 3 m2).
        yield 'bande plus large que la plaque' => [[new BandeRetour('cote', 1500, 1300), new BandeRetour('cote', 1500, 1300)], 2];
    }

    /**
     * @param list<BandeRetour> $bandes
     */
    #[DataProvider('cas')]
    public function test_plaques_des_bandes_de_retour(array $bandes, int $plaques): void
    {
        self::assertSame($plaques, (new CalepinageBandes())->plaques($bandes, 1200, 2500));
    }

    public function test_dimensions_de_plaque_invalides(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalepinageBandes())->plaques([], 0, 2500);
    }

    public function test_rouleaux_marge_et_fixations(): void
    {
        self::assertSame(2, Consommables::rouleaux(10000, 1, 10000));
        self::assertSame(1, Consommables::rouleaux(10000, 0, 10000));
        self::assertSame(3, Consommables::fixations(1400, 500));
        self::assertSame(4, Consommables::kilos(10_000_000, 350));
    }
}
