<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 20/02/2026 21:22
 */

declare(strict_types=1);


namespace App\Controller;

use App\Form\DoublageType;
use App\Model\Catalogue\AppuisIntermediaire;
use App\Model\Catalogue\Montant;
use App\Model\Catalogue\Rail;
use App\Model\Mur;
use App\Service\MateriauxCalculator;
use App\Service\OssatureCloisonCalculator;
use App\Service\OssatureDoublageCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DoublageController extends AbstractController
{
    #[Route('/doublage/mur', name: 'doublage_mur')]
    public function mur(
        Request $request,
        OssatureCloisonCalculator $ossatureCloisonCalculator,
        OssatureDoublageCalculator $ossatureDoublageCalculator,
        MateriauxCalculator $materiauxCalculator
    ): Response {
        $mur = new Mur();

        //Data test
        $mur->setLargeur(7);
        $mur->setHauteur(2.5);
        $mur->addOuverture([
            'type' => 'fenetre',
            'largeur' => 1,
            'hauteur' => 1.2,
            'distance_gauche' => 1,
            'elevation' => 0.8,
        ]);
        $mur->addOuverture([
            'type' => 'porte',
            'largeur' => 0.92,
            'hauteur' => 2.05,
            'distance_gauche' => 3,
            'elevation' => 0,
        ]);

        $montant = new Montant();
        $montant->setEntraxe(0.6);
        $montant->setLargeur(0.06);
        $montant->setLongueur(3);
        $montant->setPrix(3.9);
        $rail = new Rail();
        $rail->setLongueur(3);
        $rail->setLargeur(0.045);
        $rail->setPrix(3.8);
        $appuisIntermediaire = new AppuisIntermediaire();
        $appuisIntermediaire->setPrix(58.20);
        $appuisIntermediaire->setUniteParLot(50);

        $mur->setCatalogue([
            'montant' => $montant,
            'rail' => $rail,
            'appuisIntermediaire'=>$appuisIntermediaire,
        ]);

        $form = $this->createForm(DoublageType::class, $mur);
        $form->handleRequest($request);

        $resultats = null;
        $svg = null;
        $optimisation = null;

        if ($form->isSubmitted() && $form->isValid()) {

            $resultats = [
                'surface_mur' => $mur->getSurface(),
                'surface_ouvertures' => $mur->getSurfaceOuvertures(),
                'surface_nette' => $mur->getSurfaceNette(),
            ];

            $ossature = match ($mur->getTypeStructure()) {
                Mur::TYPE_CLOISON => $ossatureCloisonCalculator->calculer(
                    $mur,
                    $montant
                ),
                Mur::TYPE_DOUBLAGE => $ossatureDoublageCalculator->calculer(
                    $mur,
                    $montant
                )
            };
            $optimisation = $materiauxCalculator->calculer(
                $ossature['appuis'] ?? [],
                array_merge($ossature['montants'], $ossature['rail_appuis'] ?? []),
                $ossature['rails'],
                [
                    'montant' => $montant,
                    'rail' => $rail,
                    'appuis-intermediaire'=>$appuisIntermediaire,
                ]
            );

            $svg = [
                'mur' => $mur,
                'ossature' => $ossature,
            ];

        }

        return $this->render('doublage/mur.html.twig', [
            'form' => $form->createView(),
            'resultats' => $resultats,
            'svg' => $svg,
            'bom' => $optimisation,
        ]);
    }
}
