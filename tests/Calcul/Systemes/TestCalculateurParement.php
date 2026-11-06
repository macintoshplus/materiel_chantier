<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Systemes;

use App\Calcul\Calepinage\CalepinagePlaques;
use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;
use App\Catalogue\Vue\PlaqueView;
use App\Geometrie\Avertissement;

/**
 * Une peau de plaques : calepinage (décalée d'une demi-plaque de la peau précédente sauf décalage explicite),
 * vis dont la longueur est l'épaisseur de parement cumulée plus 10 mm de pénétration.
 */
final readonly class TestCalculateurParement implements CalculateurCouche
{
    private const int PENETRATION = 10;

    public function __construct(private CalepinagePlaques $calepinage)
    {
    }

    public function code(): string
    {
        return 'test.parement';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $materiau = $couche->materiau ?? throw new \LogicException('The facing layer has no material.');
        $plaque = new PlaqueView($materiau);
        $boite = $couche->surface->boite();
        $decalage = $couche->couche->decalage
            ?? (null !== $contexte->calepinagePrecedent ? $this->calepinage->decalageDemiPlaque($plaque->largeur()) : 0);
        $plan = $this->calepinage->calepiner($boite->largeur(), $boite->hauteur(), $plaque->largeur(), $plaque->longueur(), $decalage);

        $avertissements = [];
        if (null !== $contexte->calepinagePrecedent) {
            $avertissement = $this->calepinage->verifierDecalage($contexte->calepinagePrecedent, $plan);
            if ($avertissement instanceof Avertissement) {
                $avertissements[] = $avertissement;
            }
        }

        $cumule = $contexte->epaisseurParementCumulee + ($couche->epaisseur() ?? $plaque->epaisseur());
        $pose = $plaque->pose();
        $derniere = $cumule >= $contexte->epaisseurParementPrevue;
        $premiere = 0 === $contexte->epaisseurParementCumulee;
        $pas = match (true) {
            $premiere && $derniere => $pose->pasVisPeauUnique,
            $premiere => $pose->pasVisPremierePeau,
            default => $pose->pasVisDernierePeau,
        };
        $longueurMontants = 0;
        foreach ($couche->surface->coupes($contexte->directionOssature, $contexte->entraxeRetenu ?? $pose->entraxeMax) as $segment) {
            $longueurMontants += $segment->longueur();
        }
        $vis = (int) ceil($longueurMontants / $pas);

        return new ResultatCouche([
            LigneBesoin::nombre('plaques_'.$couche->couche->ordre, $couche->emplacement->code, 'plaque_platre', \count($plan->plaques), ['epaisseur_mm' => $plaque->epaisseur()], $materiau),
            LigneBesoin::nombre('vis_'.$couche->couche->ordre, $couche->emplacement->code, 'visserie', $vis, ['longueur_mm' => $cumule + self::PENETRATION]),
        ], $contexte->avecCalepinage($plan)->avecEpaisseurParementCumulee($cumule), $avertissements);
    }
}
