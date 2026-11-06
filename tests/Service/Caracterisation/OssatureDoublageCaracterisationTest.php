<?php

declare(strict_types=1);

namespace App\Tests\Service\Caracterisation;

use App\Service\OssatureCloisonCalculator;
use App\Service\OssatureDoublageCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Fige le comportement actuel du doublage : mêmes éléments que la cloison + rail support d'appuis et appuis à 1,2 m.
 */
final class OssatureDoublageCaracterisationTest extends TestCase
{
    use ScenariosTrait;

    public function testMurPleinUnSeulRailSupportEtUnAppuiParMontant(): void
    {
        $calc = new OssatureDoublageCalculator(new OssatureCloisonCalculator());
        $r = $calc->calculer($this->murPlein(), $this->montant(), $this->plaque());

        // Aucune ouverture à 1,2 m : un rail support sur toute la largeur (3,0).
        self::assertCount(1, $r['rail_appuis']);
        self::assertSame(0, $r['rail_appuis'][0]->x);
        self::assertSame(3000, $r['rail_appuis'][0]->hauteur);

        // Un appui à 1,2 m sur chacun des 7 montants (le montant final fait doublon à 3,0).
        self::assertCount(7, $r['appuis']);
        foreach ($r['appuis'] as $a) {
            self::assertSame(1200, $a->y);
        }
    }

    public function testMurAvecOuverturesRailsSupportInterrompusParLesOuverturesTraversantes(): void
    {
        $calc = new OssatureDoublageCalculator(new OssatureCloisonCalculator());
        $r = $calc->calculer($this->murAvecOuvertures(), $this->montant(), $this->plaque());

        // Fenêtre (0,8 à 2,0) et porte (0 à 2,05) croisent 1,2 m : le rail est coupé à chacune.
        // 0,048 -> 1,0 (0,952) ; 2,0 -> 3,0 (1,0) ; 3,92 -> 6,952 (3,032).
        self::assertCount(3, $r['rail_appuis']);
        $x = array_map(static fn ($m) => $m->x, $r['rail_appuis']);
        $l = array_map(static fn ($m) => $m->hauteur, $r['rail_appuis']);
        self::assertSame([48, 2000, 3920], $x);
        self::assertSame([952, 1000, 3032], $l);

        // Appuis uniquement sur les montants porteurs d'appui : 8 pleins + final + 4 d'ouverture = 13.
        self::assertCount(13, $r['appuis']);
        // Le reste du résultat est celui de la cloison.
        self::assertCount(19, $r['montants']);
        self::assertCount(5, $r['rails']);
    }

    public function testOuvertureQuiNeCroisePasLaHauteurDesAppuisNeCoupePasLeRail(): void
    {
        $mur = $this->murPlein();
        // Fenêtre haute (1,5 à 2,0) : au-dessus des appuis.
        $mur->addOuverture([
            'type' => 'fenetre', 'largeur' => 1000, 'hauteur' => 500, 'distance_gauche' => 1000, 'elevation' => 1500,
            'type_pose' => 'applique', 'type_contour' => 'aucun', 'refoncement_ouvrant' => 0,
        ]);
        $calc = new OssatureDoublageCalculator(new OssatureCloisonCalculator());
        $r = $calc->calculer($mur, $this->montant(), $this->plaque());

        self::assertCount(1, $r['rail_appuis']);
        self::assertSame(3000, $r['rail_appuis'][0]->hauteur);
    }
}
