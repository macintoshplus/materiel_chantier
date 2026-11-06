<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Systemes;

use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;
use App\Catalogue\Vue\ProfilView;
use App\Geometrie\Avertissement;

/**
 * Montants à l'entraxe du profil ; avertit si l'épaisseur isolée prévue dépasse la profondeur du profil.
 */
final class TestCalculateurOssature implements CalculateurCouche
{
    public function code(): string
    {
        return 'test.ossature';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $materiau = $couche->materiau ?? throw new \LogicException('The framing layer has no material.');
        $profil = new ProfilView($materiau);
        $entraxe = $profil->entraxeDefaut();
        $pieces = [];
        foreach ($couche->surface->coupes($contexte->directionOssature, $entraxe) as $segment) {
            $pieces[] = $segment->longueur();
        }
        $avertissements = [];
        if ($profil->profondeur() < $contexte->profondeurCavite) {
            $avertissements[] = new Avertissement('insulation_deeper_than_framing', ['cavity' => $contexte->profondeurCavite, 'profile' => $profil->profondeur()]);
        }

        return new ResultatCouche(
            [LigneBesoin::pieces('montants', $couche->emplacement->code, 'profil_ossature', $pieces, $contexte->directionOssature, ['profondeur_mm' => $profil->profondeur()], $materiau)],
            $contexte->avecEntraxe($entraxe),
            $avertissements,
        );
    }
}
