<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Cadre;

use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;
use App\Geometrie\Direction;

/**
 * Appuis de fixation d'un doublage : un tous les 1200 mm de chaque montant à l'entraxe (à calculer après
 * l'ossature, qui publie l'entraxe retenu). Leur longueur est l'épaisseur isolée prévue.
 */
final class CalculateurAttache implements CalculateurCouche
{
    public const int PAS_APPUIS = 1200;

    public function code(): string
    {
        return 'attache.appuis';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $nombre = 0;
        foreach ($couche->surface->coupes(Direction::Verticale, $contexte->entraxeRetenu ?? 600) as $segment) {
            $nombre += intdiv($segment->longueur(), self::PAS_APPUIS);
        }

        return new ResultatCouche([
            LigneBesoin::nombre('appuis', $couche->emplacement->code, 'appui_fixation', $nombre, ['longueur_mm' => $contexte->epaisseurIsoleePrevue], $couche->materiau),
        ], $contexte);
    }
}
