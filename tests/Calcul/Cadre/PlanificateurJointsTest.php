<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Cadre;

use App\Calcul\Cadre\MontantComposite;
use App\Calcul\Cadre\PlanificateurJoints;
use App\Catalogue\Vue\Renfort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlanificateurJointsTest extends TestCase
{
    public function test_premiere_ligne_a_la_longueur_de_barre_seconde_a_la_moitie(): void
    {
        self::assertSame([[3000], [1500]], PlanificateurJoints::joints(4000, 3000, 300));
    }

    public function test_montant_plus_court_qu_une_barre_sans_joint(): void
    {
        self::assertSame([[], []], PlanificateurJoints::joints(2800, 3000, 300));
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function cas(): iterable
    {
        yield 'un joint par ligne' => [4000, 3000, 300];
        yield 'plusieurs joints' => [9500, 3000, 400];
        yield 'decalage a ajuster' => [3500, 3000, 1800];
        yield 'longueur multiple de la barre' => [6000, 3000, 500];
    }

    #[DataProvider('cas')]
    public function test_les_joints_des_deux_lignes_sont_decales_et_les_segments_tiennent_dans_une_barre(int $longueur, int $barre, int $decalage): void
    {
        [$l1, $l2] = PlanificateurJoints::joints($longueur, $barre, $decalage);

        foreach ($l2 as $p2) {
            foreach ($l1 as $p1) {
                self::assertGreaterThanOrEqual($decalage, abs($p2 - $p1), 'ecart minimal entre les joints des deux lignes');
            }
        }
        foreach ([$l1, $l2] as $joints) {
            self::assertSame($joints, array_values(array_unique($joints)), 'pas deux joints au meme endroit');
            $segments = PlanificateurJoints::segments($longueur, $joints);
            self::assertSame($longueur, array_sum($segments), 'la ligne couvre toute la longueur');
            foreach ($segments as $segment) {
                self::assertLessThanOrEqual($barre, $segment);
            }
        }
    }

    public function test_montant_composite_decale_deux_lignes_sur_toute_la_longueur(): void
    {
        $c = MontantComposite::creer(4000, Renfort::DoubleDecale, 3000, 300);

        self::assertSame([[3000, 1000], [1500, 2500]], $c->lignes);
        self::assertSame(2, $c->nombreJoints());
        self::assertSame(2, $c->nombreLignes());
        self::assertSame(8000, array_sum($c->segments()), 'longueur de profile x2');
    }

    public function test_montant_composite_decale_plus_court_qu_une_barre(): void
    {
        $c = MontantComposite::creer(2800, Renfort::DoubleDecale, 3000, 300);

        self::assertSame([[2800], [2800]], $c->lignes);
        self::assertSame(0, $c->nombreJoints());
    }

    public function test_montant_composite_accole_et_simple(): void
    {
        self::assertSame([[2500], [2500]], MontantComposite::creer(2500, Renfort::DoubleAccole, 3000)->lignes);
        self::assertSame([[2500]], MontantComposite::creer(2500, Renfort::Simple, 3000)->lignes);
    }
}
