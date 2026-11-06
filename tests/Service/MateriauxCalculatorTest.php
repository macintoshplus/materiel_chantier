<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Model\Catalogue\AppuisIntermediaire;
use App\Model\Catalogue\Montant;
use App\Model\Chantier\Appuis;
use App\Model\Chantier\Montant as MontantChantier;
use App\Service\MateriauxCalculator;
use PHPUnit\Framework\TestCase;

final class MateriauxCalculatorTest extends TestCase
{
    public function testOptimiseLesMontantsDansLesBarres(): void
    {
        $montant = new Montant();
        $montant->setLongueur(3000);
        $montant->setPrix(4.0);
        $montant->setUniteParLot(1);
        $appuis = new AppuisIntermediaire();
        $appuis->setPrix(50.0);
        $appuis->setUniteParLot(50);

        // 2 x 1,5 m tiennent dans une barre de 3 m, 2,5 m en demande une seconde.
        $montants = [
            new MontantChantier('a', 0, 0, 1500, true),
            new MontantChantier('b', 600, 0, 1500, true),
            new MontantChantier('c', 1200, 0, 2500, true),
        ];

        $res = (new MateriauxCalculator())->calculer(
            appuis: [new Appuis(0, 1200)],
            montants: $montants,
            rails: [],
            plaquePlatres: [],
            longueursStandard: ['montant' => $montant],
            appuisIntermediaire: $appuis,
        );

        self::assertSame(2, $res['montant']['barres_necessaires']);
        self::assertEqualsWithDelta(8.0, $res['montant']['prix_total'], 0.001);
        self::assertSame(1.0, (float) $res[0]['nombre_lot']);
    }
}
