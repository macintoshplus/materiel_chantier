<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 21/02/2026 23:31
 */

declare(strict_types=1);

namespace App\Service;


use App\Model\Catalogue\Montant;
use App\Model\Catalogue\PlaquePlatre;
use App\Model\Chantier\Appuis;
use App\Model\Chantier\Montant as MontantChantier;
use App\Model\Chantier\PlaquePlatre as PlaquePlatreChantier;
use App\Model\Chantier\Rail;
use App\Model\Mur;

final class OssatureDoublageCalculator implements OssatureCalculatorInterface
{
    // en millimètres
    const int ELEVATION_APPUIS = 1200;
    public function __construct(private readonly OssatureCloisonCalculator $calculator)
    {
    }

    /**
     * @return array{rails: list<Rail>, montants: list<MontantChantier>, plaque_platres: list<PlaquePlatreChantier>, appuis: list<Appuis>, rail_appuis: list<MontantChantier>}
     */
    public function calculer(Mur $mur, Montant $montant, PlaquePlatre $plaquePlatre): array
    {
        $result = $this->calculator->calculer($mur, $montant, $plaquePlatre);

        $longeurMax = $mur->getLargeur() - $mur->getEcartDebut() - $mur->getEcartFin();

        $ouvertureTrier =$mur->getOuvertures();
        usort($ouvertureTrier, function ($a, $b) {
            return $a['distance_gauche'] <=> $b['distance_gauche'];
        });

        //Calculer la longeur ayant besoin d'un rail pour les appuis
        $departGaucheRail = $mur->getEcartDebut();

        foreach ($ouvertureTrier as $ouverture) {
            //L'ouverture est présente à 1,2m ?
            if ($ouverture['elevation'] <= self::ELEVATION_APPUIS
            && $ouverture['elevation'] + $ouverture['hauteur'] >= self::ELEVATION_APPUIS
            ) {
                $longeurRail = $ouverture['distance_gauche'] - $departGaucheRail;
                $result['rail_appuis'][] = new MontantChantier(
                    id: 'Rail support appuis',
                    x: $departGaucheRail,
                    y: 0,
                    hauteur: $longeurRail,
                    appuis: false,
                );
                $departGaucheRail = $ouverture['distance_gauche'] + $ouverture['largeur'];
            }
        }

        $result['rail_appuis'][] = new MontantChantier(
            id: 'Rail support appuis',
            x: $departGaucheRail,
            y: 0,
            hauteur: $longeurMax - $departGaucheRail + $mur->getEcartDebut(),
            appuis: false,
        );

        $result['appuis'] = [];
        foreach ($result['montants'] as $montant) {
            if ($montant->appuis === false) {
                continue;
            }

            $result['appuis'][] = new Appuis(
                $montant->x,
                self::ELEVATION_APPUIS,
            );
        }


        return $result;
    }
}
