<?php

declare(strict_types=1);

namespace App\Tests\Calcul;

use App\Calcul\Calepinage\CalepinagePlaques;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CalepinagePlaquesTest extends TestCase
{
    public function test_sans_decalage_la_grille_est_celle_du_calculateur_historique(): void
    {
        $c = (new CalepinagePlaques())->calepiner(3000, 2500, 1200, 2500);

        self::assertSame([1200, 1200, 600], array_map(static fn ($p): int => $p->largeur, $c->plaques));
        self::assertSame([0, 1200, 2400], array_map(static fn ($p): int => $p->x, $c->plaques));
        self::assertSame([1200, 2400], $c->jointsVerticaux());
    }

    public function test_une_demi_plaque_de_decalage_deplace_les_joints_de_60_cm(): void
    {
        $calepinage = new CalepinagePlaques();
        $premiere = $calepinage->calepiner(3000, 2500, 1200, 2500);
        $seconde = $calepinage->calepiner(3000, 2500, 1200, 2500, $calepinage->decalageDemiPlaque(1200));

        self::assertSame([600, 1800], $seconde->jointsVerticaux());
        self::assertSame(600, $premiere->ecartMinimalJoints($seconde));
        self::assertNull($calepinage->verifierDecalage($premiere, $seconde));
    }

    public function test_des_joints_trop_proches_donnent_un_avertissement(): void
    {
        $calepinage = new CalepinagePlaques();
        $premiere = $calepinage->calepiner(3000, 2500, 1200, 2500);
        $seconde = $calepinage->calepiner(3000, 2500, 1200, 2500, 300);

        $a = $calepinage->verifierDecalage($premiere, $seconde);
        self::assertSame('layer_joints_too_close', $a?->code);
        self::assertSame(300, $a->params['actual']);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function zones(): iterable
    {
        yield 'mur plein' => [3000, 2500, 0];
        yield 'décalé d une demi-plaque' => [3000, 2500, 600];
        yield 'deux rangées' => [3700, 4100, 600];
        yield 'plus étroit qu une plaque' => [900, 2500, 600];
    }

    #[DataProvider('zones')]
    public function test_les_plaques_recouvrent_exactement_la_zone(int $largeur, int $hauteur, int $decalage): void
    {
        $c = (new CalepinagePlaques())->calepiner($largeur, $hauteur, 1200, 2500, $decalage);

        self::assertSame($largeur * $hauteur, $c->surfacePlaques());
    }
}
