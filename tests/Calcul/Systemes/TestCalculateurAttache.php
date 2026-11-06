<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Systemes;

use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;

/**
 * Une attache tous les 600 x 600 mm ; sa longueur est l'épaisseur isolée prévue (cumul de tous les isolants).
 */
final class TestCalculateurAttache implements CalculateurCouche
{
    public function code(): string
    {
        return 'test.attache';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $nombre = (int) ceil($couche->surface->surfaceNette() / (600 * 600));

        return new ResultatCouche([
            LigneBesoin::nombre('attaches', $couche->emplacement->code, 'appui_fixation', $nombre, ['longueur_mm' => $contexte->epaisseurIsoleePrevue], $couche->materiau),
        ], $contexte);
    }
}
