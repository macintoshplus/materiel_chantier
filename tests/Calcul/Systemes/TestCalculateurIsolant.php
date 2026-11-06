<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Systemes;

use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;
use App\Catalogue\Vue\IsolantView;

/**
 * Bandes d'isolant de la largeur de l'article, coupées parallèlement (0 degré) ou perpendiculairement (90 degrés)
 * à l'ossature ; ajoute son R au cumul.
 */
final class TestCalculateurIsolant implements CalculateurCouche
{
    public function code(): string
    {
        return 'test.isolant';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $materiau = $couche->materiau ?? throw new \LogicException('The insulation layer has no material.');
        $vue = new IsolantView($materiau);
        $largeur = $materiau->valeur('largeur');
        $direction = $couche->directionCoupes($contexte);
        $pieces = [];
        foreach ($couche->surface->coupes($direction, \is_int($largeur) ? $largeur : 600) as $segment) {
            $pieces[] = $segment->longueur();
        }
        $epaisseur = $couche->epaisseur() ?? $vue->epaisseur();

        return new ResultatCouche(
            [LigneBesoin::pieces('isolant_'.$couche->couche->ordre, $couche->emplacement->code, 'isolant', $pieces, $direction, ['epaisseur_mm' => $epaisseur], $materiau)],
            $contexte->avecRCumuleMilli($contexte->rCumuleMilli + $vue->resistanceMilli($epaisseur)),
        );
    }
}
