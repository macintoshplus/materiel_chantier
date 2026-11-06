<?php

declare(strict_types=1);

namespace App\Tests\Service\Caracterisation;

use App\Model\Catalogue\AppuisIntermediaire;
use App\Model\Catalogue\BandeAJointArmee;
use App\Model\Catalogue\BandeAJointPlat;
use App\Model\Catalogue\Montant;
use App\Model\Catalogue\PlaquePlatre;
use App\Model\Catalogue\Rail;
use App\Model\Mur;

/**
 * Scénarios partagés par les tests de caractérisation (valeurs en millimètres).
 */
trait ScenariosTrait
{
    private function montant(): Montant
    {
        $m = new Montant();
        $m->setEntraxe(600);
        $m->setLargeur(60);
        $m->setLongueur(3000);
        $m->setPrix(3.9);

        return $m;
    }

    private function rail(): Rail
    {
        $r = new Rail();
        $r->setLongueur(3000);
        $r->setLargeur(45);
        $r->setPrix(3.8);

        return $r;
    }

    private function plaque(): PlaquePlatre
    {
        $p = new PlaquePlatre();
        $p->setLongueur(2500);
        $p->setLargeur(1200);
        $p->setType(PlaquePlatre::TYPE_STD);
        $p->setPrix(7.95);
        $p->setUniteParLot(1);

        return $p;
    }

    private function appuis(): AppuisIntermediaire
    {
        $a = new AppuisIntermediaire();
        $a->setPrix(58.20);
        $a->setUniteParLot(50);

        return $a;
    }

    private function bandePlat(): BandeAJointPlat
    {
        $b = new BandeAJointPlat();
        $b->setLongueur(75000);
        $b->setPrix(59.00);
        $b->setUniteParLot(5);

        return $b;
    }

    private function bandeArmee(): BandeAJointArmee
    {
        $b = new BandeAJointArmee();
        $b->setLongueur(30000);
        $b->setPrix(63.90);
        $b->setUniteParLot(3);

        return $b;
    }

    /** Scénario A : mur plein de 3 000 x 2 500 mm, sans écart ni ouverture. */
    private function murPlein(): Mur
    {
        $mur = new Mur();
        $mur->setLargeur(3000);
        $mur->setHauteur(2500);
        $mur->setEcartDebut(0);
        $mur->setEcartFin(0);

        return $mur;
    }

    /** Scénario B : mur de 7 x 2,5 avec écarts de 48 mm, une fenêtre et une porte (écran actuel). */
    private function murAvecOuvertures(): Mur
    {
        $mur = new Mur();
        $mur->setLargeur(7000);
        $mur->setHauteur(2500);
        $mur->addOuverture([
            'type' => 'fenetre', 'largeur' => 1000, 'hauteur' => 1200, 'distance_gauche' => 1000, 'elevation' => 800,
            'type_pose' => 'tunel', 'type_contour' => 'plaque-platre', 'refoncement_ouvrant' => 500,
        ]);
        $mur->addOuverture([
            'type' => 'porte', 'largeur' => 920, 'hauteur' => 2050, 'distance_gauche' => 3000, 'elevation' => 0,
            'type_pose' => 'tunel', 'type_contour' => 'plaque-platre', 'refoncement_ouvrant' => 500,
        ]);

        return $mur;
    }
}
