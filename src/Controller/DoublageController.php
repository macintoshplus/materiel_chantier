<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 20/02/2026 21:22
 */

declare(strict_types=1);


namespace App\Controller;

use App\Form\DoublageType;
use App\Model\Mur;
use App\Service\CatalogueHeritage;
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
        MateriauxCalculator $materiauxCalculator,
        CatalogueHeritage $catalogueHeritage,
    ): Response {
        $mur = new Mur();

        //Data test
        $mur->setLargeur(7000);
        $mur->setHauteur(2500);
        $mur->addOuverture([
            'type' => 'fenetre',
            'largeur' => 1000,
            'hauteur' => 1200,
            'distance_gauche' => 1000,
            'elevation' => 800,
            'type_pose' => 'tunel',
            'type_contour' => 'plaque-platre',
            'refoncement_ouvrant' => 500,
        ]);
        $mur->addOuverture([
            'type' => 'porte',
            'largeur' => 920,
            'hauteur' => 2050,
            'distance_gauche' => 3000,
            'elevation' => 0,
            'type_pose' => 'tunel',
            'type_contour' => 'plaque-platre',
            'refoncement_ouvrant' => 500,
        ]);

        $catalogue = $catalogueHeritage->charger(new \DateTimeImmutable());
        $montant = $catalogue['montant'];
        $rail = $catalogue['rail'];
        $appuisIntermediaire = $catalogue['appuisIntermediaire'];
        $bandeJoinPlat = $catalogue['bandeAJoinPlat'];
        $bandeJoinArmee = $catalogue['bandeAJoinArmee'];
        $plaquePlatre = $catalogue['plaquePlatre'];

        $mur->setCatalogue($catalogue);

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
                    $montant,
                    $plaquePlatre
                ),
                default => $ossatureDoublageCalculator->calculer(
                    $mur,
                    $montant,
                    $plaquePlatre
                )
            };
            $optimisation = $materiauxCalculator->calculer(
                appuis: $ossature['appuis'] ?? [],
                montants: array_merge($ossature['montants'], $ossature['rail_appuis'] ?? []),
                rails: $ossature['rails'],
                plaquePlatres: $ossature['plaque_platres'],
                longueursStandard: [
                    'montant' => $montant,
                    'rail' => $rail,
                    'plaque-platre' => $plaquePlatre,
                    'joint-armee' => $bandeJoinArmee,
                    'joint-plat' => $bandeJoinPlat,
                ],
                appuisIntermediaire: $appuisIntermediaire,
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
