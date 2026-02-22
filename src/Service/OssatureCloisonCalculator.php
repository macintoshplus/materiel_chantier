<?php

namespace App\Service;

use App\Model\Catalogue\Montant;
use App\Model\Chantier\Montant as MontantChantier;
use App\Model\Chantier\Rail;
use App\Model\Mur;

class OssatureCloisonCalculator implements OssatureCalculatorInterface
{
    public function calculer(
        Mur $mur,
        Montant $montant,
    ): array {

        $largeurMur = $mur->getLargeur();

        $hauteurMur = $mur->getHauteur();
        $ouvertures = $mur->getOuvertures();

        $ecartementGauche = $mur->getEcartDebutEnMetre();
        $ecartementDroite = $mur->getEcartFinEnMetre();

        $largeurMurADoubler = $largeurMur - $ecartementGauche - $ecartementDroite;

        // Rails
        $rails = [
            new Rail(
                id: 'bas',
                x: $ecartementGauche,
                y: 0,
                longueur: $largeurMurADoubler,
                surOuverture: false,
            ),
            new Rail(
                id: 'haut',
                x: $ecartementGauche,
                y: $hauteurMur,
                longueur: $largeurMurADoubler,
                surOuverture: false,
            ),
        ];

        //Rail ouverture
        foreach ($ouvertures as $k => $o) {
            $rails[] = new Rail(
                id: 'ouverture_' . $k . '_haut',
                x: $o['distance_gauche'],
                y: $o['elevation'] + $o['hauteur'],
                longueur: $o['largeur'],
                surOuverture: true,
            );
            if ($o['type'] === 'fenetre') {
                $rails[] = new Rail(
                    id: 'ouverture_' . $k . '_bas',
                    x: $o['distance_gauche'],
                    y: $o['elevation'],
                    longueur: $o['largeur'],
                    surOuverture: true,
                );
            }
        }

        // Montants théoriques
        $montants = [];
        $x = $ecartementGauche;

        while ($x <= ($largeurMurADoubler) + 0.001) {
            if ($this->dansOuverture($x, $ouvertures)) {
                $montants[] = new MontantChantier(
                    id: 'Au dessus d\'une ouverture',
                    x: $x,
                    y: $o['elevation'] + $o['hauteur'],
                    hauteur: $hauteurMur - ($o['elevation'] + $o['hauteur']),
                    appuis: false,
                );

                if ($o['type'] === 'fenetre') {
                    $montants[] = new MontantChantier(
                        id: 'sous une fenêtre',
                        x: $x,
                        y: 0,
                        hauteur: $o['elevation'],
                        appuis: false,
                    );
                }


                $x += $montant->getEntraxe();
                continue;
            }
            $montants[] = new MontantChantier(
                id: 'Montant',
                x: $x,
                y: 0,
                hauteur: $hauteurMur,
                appuis: true,
            );
            $x += $montant->getEntraxe();
        }

        //Montant final
        $montants[] =  new MontantChantier(
            id: 'Montant final',
            x: $largeurMur - $ecartementDroite,
            y: 0,
            hauteur: $hauteurMur,
            appuis: true,
        );

        //Montant gauche et droite des ouvertures
        foreach ($ouvertures as $o) {
            if ($this->checkMontantExists($montants, $o['distance_gauche'], $montant) === false) {
                $montants[] =  new MontantChantier(
                    id: 'montant gauche ouverture',
                    x: $o['distance_gauche'],
                    y: 0,
                    hauteur: $hauteurMur,
                    appuis: true,
                );
            }
            if ($this->checkMontantExists($montants, $o['distance_gauche'] + $o['largeur'], $montant) === false) {
                $montants[] =  new MontantChantier(
                    id: 'montant droit ouverture',
                    x: $o['distance_gauche'] + $o['largeur'],
                    y: 0,
                    hauteur: $hauteurMur,
                    appuis: true,
                );
            }
        }

        return [
            'rails' => $rails,
            'montants' => $montants,
        ];
    }

    private function dansOuverture(float $x, array $ouvertures): bool
    {
        foreach ($ouvertures as $o) {
            $xMin = $o['distance_gauche'];
            $xMax = $xMin + $o['largeur'];

            if ($x > $xMin && $x < $xMax) {
                return true;
            }
        }

        return false;
    }

    private function checkMontantExists(array $montants, float $x, Montant $montant): bool
    {
        foreach ($montants as $m) {
            $montantMin = $m->x - ($montant->getLargeur() / 2);
            $montantMax = $m->x + ($montant->getLargeur() / 2);
            if ($x > $montantMin && $x < $montantMax) {
                return true;
            }
        }
        return false;
    }
}
