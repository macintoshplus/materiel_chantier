<?php

declare(strict_types=1);

namespace App\Tests\Service\Caracterisation;

use App\Model\Catalogue\AppuisIntermediaire;
use App\Model\Chantier\Montant as MontantChantier;
use App\Model\Chantier\PlaquePlatre as Plaque;
use App\Model\Chantier\Rail as RailChantier;
use App\Service\MateriauxCalculator;
use App\Service\OssatureCloisonCalculator;
use App\Service\OssatureDoublageCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Fige le bordereau de matériaux : de l'ossature au nombre de barres, lots et prix.
 */
final class MateriauxCaracterisationTest extends TestCase
{
    use ScenariosTrait;

    /** @return array<string, \App\Model\Catalogue\ContientPrixInterface&\App\Model\Catalogue\LongueurInterface> */
    private function standards(): array
    {
        return [
            'montant' => $this->montant(),
            'rail' => $this->rail(),
            'plaque-platre' => $this->plaque(),
            'joint-armee' => $this->bandeArmee(),
            'joint-plat' => $this->bandePlat(),
        ];
    }

    public function testBordereauCloisonMurPlein(): void
    {
        $ossature = (new OssatureCloisonCalculator())->calculer($this->murPlein(), $this->montant(), $this->plaque());
        $bom = (new MateriauxCalculator())->calculer(
            appuis: [],
            montants: $ossature['montants'],
            rails: $ossature['rails'],
            plaquePlatres: $ossature['plaque_platres'],
            longueursStandard: $this->standards(),
            appuisIntermediaire: $this->appuis(),
        );

        // 7 montants de 2,5 m : un par barre de 3 m, 7 x 3,9 = 27,3.
        self::assertSame(7, $bom['montant']['barres_necessaires']);
        self::assertSame(17500, $bom['montant']['longueur_totale']);
        self::assertSame(21000, $bom['montant']['longueur_achetee']);
        self::assertSame(3500, $bom['montant']['chute']);
        self::assertEqualsWithDelta(27.3, $bom['montant']['prix_total'], 1e-9);

        // 2 rails de 3,0 m exactement : 2 barres, aucune chute, 2 x 3,8.
        self::assertSame(2, $bom['rail']['barres_necessaires']);
        self::assertSame(0, $bom['rail']['chute']);
        self::assertEqualsWithDelta(7.6, $bom['rail']['prix_total'], 1e-9);

        // 3 plaques de 2,5 m dans des barres de 2,5 m : 3 plaques à 7,95.
        self::assertSame(3, $bom['plaque-platre']['barres_necessaires']);
        self::assertEqualsWithDelta(23.85, $bom['plaque-platre']['prix_total'], 1e-9);

        // Joints plats : droits 2 x 2,5 ; hauts 1,2 + 1,2 + 0,6 ; bas 1,2 + 0,6 = 9,8 m dans un rouleau de 75 m.
        self::assertSame(7, $bom['joint-plat']['elements']);
        self::assertSame(9800, $bom['joint-plat']['longueur_totale']);
        self::assertSame(1, $bom['joint-plat']['barres_necessaires']);
        self::assertSame(65200, $bom['joint-plat']['chute']);
        // taux de perte : 65,2 / 75 = 86,93 % arrondi à 0,1.
        self::assertEqualsWithDelta(86.9, $bom['joint-plat']['taux_perte'], 1e-9);
        self::assertEqualsWithDelta(59.0, $bom['joint-plat']['prix_total'], 1e-9);
        self::assertArrayNotHasKey('joint-armee', $bom);
        self::assertArrayNotHasKey(0, $bom);
    }

    public function testBordereauDoublageMurPleinAvecAppuis(): void
    {
        $ossature = (new OssatureDoublageCalculator(new OssatureCloisonCalculator()))
            ->calculer($this->murPlein(), $this->montant(), $this->plaque());
        $bom = (new MateriauxCalculator())->calculer(
            appuis: $ossature['appuis'],
            montants: array_merge($ossature['montants'], $ossature['rail_appuis']),
            rails: $ossature['rails'],
            plaquePlatres: $ossature['plaque_platres'],
            longueursStandard: $this->standards(),
            appuisIntermediaire: $this->appuis(),
        );

        // 7 montants de 2,5 m + le rail support de 3,0 m (compté comme montant) : 8 barres, 8 x 3,9 = 31,2.
        self::assertSame(8, $bom['montant']['barres_necessaires']);
        self::assertSame(8, $bom['montant']['elements']);
        self::assertEqualsWithDelta(31.2, $bom['montant']['prix_total'], 1e-9);

        // 7 appuis, lots de 50 : 1 lot de 58,20 ; chute 43 ; perte 86 %.
        self::assertSame('appuis-intermediaire', $bom[0]['type']);
        self::assertSame(7, $bom[0]['elements']);
        self::assertEqualsWithDelta(1.0, (float) $bom[0]['nombre_lot'], 1e-9);
        self::assertEqualsWithDelta(58.2, $bom[0]['prix_total'], 1e-9);
        self::assertEqualsWithDelta(43.0, (float) $bom[0]['chute'], 1e-9);
        self::assertEqualsWithDelta(86.0, (float) $bom[0]['taux_perte'], 1e-9);
    }

    public function testJointsSortantsEtEntrants(): void
    {
        // Une plaque 1,2 x 2,5 : gauche sortant (2,5), droit entrant (2,5), haut aucun, bas sortant (1,2).
        $plaque = new Plaque('p', 0, 0, 2500, 1200, Plaque::TYPE_JOINT_SORTANT, Plaque::TYPE_JOINT_ENTRANT, Plaque::TYPE_JOINT_AUCUN, Plaque::TYPE_JOINT_SORTANT);
        $bom = (new MateriauxCalculator())->calculer(
            appuis: [],
            montants: [],
            rails: [],
            plaquePlatres: [$plaque],
            longueursStandard: $this->standards(),
            appuisIntermediaire: $this->appuis(),
        );

        self::assertSame(3700, $bom['joint-armee']['longueur_totale']);
        self::assertSame(2, $bom['joint-armee']['elements']);
        self::assertEqualsWithDelta(63.9, $bom['joint-armee']['prix_total'], 1e-9);
        self::assertSame(2500, $bom['joint-plat']['longueur_totale']);
        self::assertEqualsWithDelta(59.0, $bom['joint-plat']['prix_total'], 1e-9);
    }

    public function testPlusieursLotsPourLesLongsElements(): void
    {
        // 4 montants de 2,0 m : 3 m ne tiennent qu'un montant chacun (2 + 2 > 3) -> 4 barres.
        $montants = [];
        for ($i = 0; $i < 4; ++$i) {
            $montants[] = new MontantChantier('m' . $i, 600 * $i, 0, 2000, true);
        }
        // Lot de 3 barres pour tester l'arrondi au lot supérieur.
        $montant = $this->montant();
        $montant->setUniteParLot(3);

        $bom = (new MateriauxCalculator())->calculer(
            appuis: [],
            montants: $montants,
            rails: [],
            plaquePlatres: [],
            longueursStandard: ['montant' => $montant],
            appuisIntermediaire: new AppuisIntermediaire(),
        );

        self::assertSame(4, $bom['montant']['barres_necessaires']);
        // ceil(4 / 3) = 2 lots x 3,9.
        self::assertEqualsWithDelta(2.0, (float) $bom['montant']['nombre_lot'], 1e-9);
        self::assertEqualsWithDelta(7.8, $bom['montant']['prix_total'], 1e-9);
    }

    public function testRailsDeFixationDOuvertureAjoutentVingtCentimetres(): void
    {
        // Rail d'ouverture de 2,9 m + 0,2 m de fixation = 3,1 m > 3 m : il faut 2 barres.
        $bom = (new MateriauxCalculator())->calculer(
            appuis: [],
            montants: [],
            rails: [new RailChantier('o', 0, 0, 2900, true)],
            plaquePlatres: [],
            longueursStandard: ['rail' => $this->rail()],
            appuisIntermediaire: $this->appuis(),
        );
        self::assertSame(2, $bom['rail']['barres_necessaires']);
        self::assertSame(3100, $bom['rail']['longueur_totale']);

        // Même rail hors ouverture : 2,9 m tiennent dans une barre.
        $bom = (new MateriauxCalculator())->calculer(
            appuis: [],
            montants: [],
            rails: [new RailChantier('o', 0, 0, 2900, false)],
            plaquePlatres: [],
            longueursStandard: ['rail' => $this->rail()],
            appuisIntermediaire: $this->appuis(),
        );
        self::assertSame(1, $bom['rail']['barres_necessaires']);
    }

    public function testBordereauCloisonAvecOuverturesMontantsEtRails(): void
    {
        $ossature = (new OssatureCloisonCalculator())->calculer($this->murAvecOuvertures(), $this->montant(), $this->plaque());
        $bom = (new MateriauxCalculator())->calculer(
            appuis: [],
            montants: $ossature['montants'],
            rails: $ossature['rails'],
            plaquePlatres: $ossature['plaque_platres'],
            longueursStandard: $this->standards(),
            appuisIntermediaire: $this->appuis(),
        );

        // Montants : 13 x 2,5 (une barre chacun, reste 0,5) ; 2 x 0,8 dans une 14e barre ; les 0,5 puis 0,45
        // se rangent dans les restes des premières barres. Longueur totale : 32,5 + 1,6 + 1,0 + 0,9 = 36,0.
        self::assertSame(19, $bom['montant']['elements']);
        self::assertSame(36000, $bom['montant']['longueur_totale']);
        self::assertSame(14, $bom['montant']['barres_necessaires']);
        self::assertEqualsWithDelta(54.6, $bom['montant']['prix_total'], 1e-9);

        // Rails : 2 x 6,904 + 1,2 + 1,2 + 1,12 = 17,328 m. Comportement actuel (figé tel quel) : 7 barres ;
        // un découpage optimal en demanderait 6 (voir le commentaire de l'algorithme).
        self::assertSame(5, $bom['rail']['elements']);
        self::assertSame(17328, $bom['rail']['longueur_totale']);
        self::assertSame(7, $bom['rail']['barres_necessaires']);
    }

    public function testStandardManquantLeveUneException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Longueur standard manquante pour rail');

        (new MateriauxCalculator())->calculer(
            appuis: [],
            montants: [],
            rails: [new RailChantier('o', 0, 0, 1000, false)],
            plaquePlatres: [],
            longueursStandard: [],
            appuisIntermediaire: $this->appuis(),
        );
    }
}
