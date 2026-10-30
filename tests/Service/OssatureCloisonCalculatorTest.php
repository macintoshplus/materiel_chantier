<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Model\Catalogue\Montant;
use App\Model\Catalogue\PlaquePlatre;
use App\Model\Mur;
use App\Service\OssatureCloisonCalculator;
use PHPUnit\Framework\TestCase;

final class OssatureCloisonCalculatorTest extends TestCase
{
    public function testMontantsAuDessusDeLOuvertureQuiLaContient(): void
    {
        $mur = new Mur();
        $mur->setLargeur(6.0);
        $mur->setHauteur(2.5);
        $mur->setEcartDebut(0);
        $mur->setEcartFin(0);
        // Fenêtre à gauche, puis porte à droite : les montants au-dessus de la fenêtre
        // ne doivent pas utiliser les dimensions de la porte (dernière ouverture).
        $mur->addOuverture($this->ouverture('fenetre', 1.0, 1.2, 1.0, 0.8));
        $mur->addOuverture($this->ouverture('porte', 0.9, 2.05, 4.0, 0.0));

        $montant = new Montant();
        $montant->setLargeur(0.06);
        $montant->setEntraxe(0.6);
        $plaque = new PlaquePlatre();
        $plaque->setLongueur(2.5);
        $plaque->setLargeur(1.2);

        $result = (new OssatureCloisonCalculator())->calculer($mur, $montant, $plaque);

        $auDessusFenetre = array_filter(
            $result['montants'],
            static fn ($m) => $m->id === 'Au dessus d\'une ouverture' && $m->x > 1.0 && $m->x < 2.0,
        );
        self::assertNotEmpty($auDessusFenetre);
        foreach ($auDessusFenetre as $m) {
            self::assertEqualsWithDelta(2.0, $m->y, 0.001);
            self::assertEqualsWithDelta(0.5, $m->hauteur, 0.001);
        }

        $sousFenetre = array_filter($result['montants'], static fn ($m) => $m->id === 'sous une fenêtre');
        self::assertNotEmpty($sousFenetre);
    }

    /**
     * @return array{type: string, largeur: float, hauteur: float, distance_gauche: float, elevation: float, type_pose: string, type_contour: string, refoncement_ouvrant: float}
     */
    private function ouverture(string $type, float $largeur, float $hauteur, float $gauche, float $elevation): array
    {
        return [
            'type' => $type,
            'largeur' => $largeur,
            'hauteur' => $hauteur,
            'distance_gauche' => $gauche,
            'elevation' => $elevation,
            'type_pose' => 'tunel',
            'type_contour' => 'plaque-platre',
            'refoncement_ouvrant' => 0.5,
        ];
    }
}
