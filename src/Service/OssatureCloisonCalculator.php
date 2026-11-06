<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Calepinage\CalepinagePlaques;
use App\Model\Catalogue\Montant;
use App\Model\Chantier\Montant as MontantChantier;
use App\Model\Catalogue\PlaquePlatre;
use App\Model\Chantier\PlaquePlatre as PlaquePlatreChantier;
use App\Model\Chantier\Rail;
use App\Model\Mur;

class OssatureCloisonCalculator implements OssatureCalculatorInterface
{
    /**
     * @return array{rails: list<Rail>, montants: list<MontantChantier>, contour_ouvrant: list<PlaquePlatreChantier>, plaque_platres: list<PlaquePlatreChantier>}
     */
    public function calculer(
        Mur $mur,
        Montant $montant,
        PlaquePlatre $plaquePlatre
    ): array {

        $largeurMur = $mur->getLargeur();

        $hauteurMur = $mur->getHauteur();
        $ouvertures = $mur->getOuvertures();

        $ecartementGauche = $mur->getEcartDebut();
        $ecartementDroite = $mur->getEcartFin();

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

        while ($x <= $largeurMurADoubler) {
            $o = $this->ouvertureA($x, $ouvertures);
            if ($o !== null) {
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
        $montants[] = new MontantChantier(
            id: 'Montant final',
            x: $largeurMur - $ecartementDroite,
            y: 0,
            hauteur: $hauteurMur,
            appuis: true,
        );

        //Montant gauche et droite des ouvertures
        foreach ($ouvertures as $o) {
            if ($this->checkMontantExists($montants, $o['distance_gauche'], $montant) === false) {
                $montants[] = new MontantChantier(
                    id: 'montant gauche ouverture',
                    x: $o['distance_gauche'],
                    y: 0,
                    hauteur: $hauteurMur,
                    appuis: true,
                );
            }
            if ($this->checkMontantExists($montants, $o['distance_gauche'] + $o['largeur'], $montant) === false) {
                $montants[] = new MontantChantier(
                    id: 'montant droit ouverture',
                    x: $o['distance_gauche'] + $o['largeur'],
                    y: 0,
                    hauteur: $hauteurMur,
                    appuis: true,
                );
            }
        }

        $contourOuvrant = [];
        foreach ($ouvertures as $k => $o) {
            if ($o['type_pose'] === 'applique') {
                continue;
            }
            //Gauche
            $contourOuvrant[] = new PlaquePlatreChantier(
                id: 'countour ouvrant ' . $k . ' gauche',
                x: $o['distance_gauche'],
                y: $o['elevation'],
                longeur: $o['hauteur'],
                largeur: $o['refoncement_ouvrant'],
                typeJointGauche: $o['type_contour'] === 'plaque-platre' ? PlaquePlatreChantier::TYPE_JOINT_SORTANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointDroit: PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointHaut: $o['type_contour'] === 'plaque-platre' ? PlaquePlatreChantier::TYPE_JOINT_ENTRANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointBas: $o['type_contour'] === 'plaque-platre' && $o['type'] === 'fenetre' ? PlaquePlatreChantier::TYPE_JOINT_ENTRANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
            );
            //Droit
            $contourOuvrant[] = new PlaquePlatreChantier(
                id: 'countour ouvrant ' . $k . ' droit',
                x: $o['distance_gauche'] + $o['largeur'],
                y: $o['elevation'],
                longeur: $o['hauteur'],
                largeur: $o['refoncement_ouvrant'],
                typeJointGauche: PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointDroit: $o['type_contour'] === 'plaque-platre' ? PlaquePlatreChantier::TYPE_JOINT_SORTANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointHaut: $o['type_contour'] === 'plaque-platre' ? PlaquePlatreChantier::TYPE_JOINT_ENTRANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointBas: $o['type_contour'] === 'plaque-platre' && $o['type'] === 'fenetre' ? PlaquePlatreChantier::TYPE_JOINT_ENTRANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
            );
            //Haut
            $contourOuvrant[] = new PlaquePlatreChantier(
                id: 'countour ouvrant ' . $k . ' haut',
                x: $o['distance_gauche'],
                y: $o['elevation'] + $o['hauteur'],
                longeur: $o['largeur'],
                largeur: $o['refoncement_ouvrant'],
                typeJointGauche: $o['type_contour'] === 'plaque-platre' ? PlaquePlatreChantier::TYPE_JOINT_SORTANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointDroit: PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointHaut: $o['type_contour'] === 'plaque-platre' ? PlaquePlatreChantier::TYPE_JOINT_ENTRANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointBas: $o['type_contour'] === 'plaque-platre' ? PlaquePlatreChantier::TYPE_JOINT_ENTRANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
            );
            if ($o['type'] === 'porte') {
                continue;
            }
            //Bas
            $contourOuvrant[] = new PlaquePlatreChantier(
                id: 'countour ouvrant ' . $k . ' bas',
                x: $o['distance_gauche'],
                y: $o['elevation'],
                longeur: $o['largeur'],
                largeur: $o['refoncement_ouvrant'],
                typeJointGauche: PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointDroit: $o['type_contour'] === 'plaque-platre' ? PlaquePlatreChantier::TYPE_JOINT_SORTANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointHaut: $o['type_contour'] === 'plaque-platre' ? PlaquePlatreChantier::TYPE_JOINT_ENTRANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointBas: $o['type_contour'] === 'plaque-platre' ? PlaquePlatreChantier::TYPE_JOINT_ENTRANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
            );
        }

        $plaquePlatres = $contourOuvrant;

        $id = 1;
        $calepinage = (new CalepinagePlaques())->calepiner($largeurMurADoubler, $mur->getHauteur(), $plaquePlatre->getLargeur(), $plaquePlatre->getLongueur());

        foreach ($calepinage->plaques as $p) {
            $plaquePlatres[] = new PlaquePlatreChantier(
                id: 'plaque ' . $id++ . ' ligne ' . ($p->ligne + 1) . ' colonne ' . ($p->colonne + 1),
                x: $p->x,
                y: $p->y,
                longeur: $p->hauteur,
                largeur: $p->largeur,
                typeJointGauche: $p->ligne > 0 ? PlaquePlatreChantier::TYPE_JOINT_ENTRANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
                typeJointDroit: $p->largeur < $plaquePlatre->getLargeur() ? PlaquePlatreChantier::TYPE_JOINT_AUCUN : PlaquePlatreChantier::TYPE_JOINT_ENTRANT,
                typeJointHaut: $p->hauteur < $plaquePlatre->getLongueur() ? PlaquePlatreChantier::TYPE_JOINT_AUCUN : PlaquePlatreChantier::TYPE_JOINT_ENTRANT,
                typeJointBas: $p->colonne > 0 ? PlaquePlatreChantier::TYPE_JOINT_ENTRANT : PlaquePlatreChantier::TYPE_JOINT_AUCUN,
            );
        }

        return [
            'rails' => $rails,
            'montants' => $montants,
            'contour_ouvrant' => $contourOuvrant,
            'plaque_platres' => $plaquePlatres,
        ];
    }

    /**
     * @param list<array{type: string, largeur: int, hauteur: int, distance_gauche: int, elevation: int, type_pose: string, type_contour: string, refoncement_ouvrant: int}> $ouvertures
     * @return array{type: string, largeur: int, hauteur: int, distance_gauche: int, elevation: int, type_pose: string, type_contour: string, refoncement_ouvrant: int}|null
     */
    private function ouvertureA(int $x, array $ouvertures): ?array
    {
        foreach ($ouvertures as $o) {
            $xMin = $o['distance_gauche'];
            $xMax = $xMin + $o['largeur'];

            if ($x > $xMin && $x < $xMax) {
                return $o;
            }
        }

        return null;
    }

    /**
     * @param list<MontantChantier> $montants
     */
    private function checkMontantExists(array $montants, int $x, Montant $montant): bool
    {
        foreach ($montants as $m) {
            $demiLargeur = $montant->getLargeur() / 2;
            $montantMin = $m->x - $demiLargeur;
            $montantMax = $m->x + $demiLargeur;
            if ($x > $montantMin && $x < $montantMax) {
                return true;
            }
        }
        return false;
    }
}
