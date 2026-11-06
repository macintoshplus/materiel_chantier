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
        $mur->setLargeur(6000);
        $mur->setHauteur(2500);
        $mur->setEcartDebut(0);
        $mur->setEcartFin(0);
        // Fenêtre à gauche, puis porte à droite : les montants au-dessus de la fenêtre
        // ne doivent pas utiliser les dimensions de la porte (dernière ouverture).
        $mur->addOuverture($this->ouverture('fenetre', 1000, 1200, 1000, 800));
        $mur->addOuverture($this->ouverture('porte', 900, 2050, 4000, 0));

        $montant = new Montant();
        $montant->setLargeur(60);
        $montant->setEntraxe(600);
        $plaque = new PlaquePlatre();
        $plaque->setLongueur(2500);
        $plaque->setLargeur(1200);

        $result = (new OssatureCloisonCalculator())->calculer($mur, $montant, $plaque);

        $auDessusFenetre = array_filter(
            $result['montants'],
            static fn ($m) => $m->id === 'Au dessus d\'une ouverture' && $m->x > 1000 && $m->x < 2000,
        );
        self::assertNotEmpty($auDessusFenetre);
        foreach ($auDessusFenetre as $m) {
            self::assertSame(2000, $m->y);
            self::assertSame(500, $m->hauteur);
        }

        $sousFenetre = array_filter($result['montants'], static fn ($m) => $m->id === 'sous une fenêtre');
        self::assertNotEmpty($sousFenetre);
    }

    /**
     * @return array{type: string, largeur: int, hauteur: int, distance_gauche: int, elevation: int, type_pose: string, type_contour: string, refoncement_ouvrant: int}
     */
    private function ouverture(string $type, int $largeur, int $hauteur, int $gauche, int $elevation): array
    {
        return [
            'type' => $type,
            'largeur' => $largeur,
            'hauteur' => $hauteur,
            'distance_gauche' => $gauche,
            'elevation' => $elevation,
            'type_pose' => 'tunel',
            'type_contour' => 'plaque-platre',
            'refoncement_ouvrant' => 500,
        ];
    }
}
