<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Plafond;

use App\Calcul\Cadre\FourruresPlafond;
use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;

/**
 * Suspentes d'un plafond : une par `pas` le long de chaque fourrure (au moins une), au pas retenu par la
 * planification (défaut du système ou limite de l'article). La longueur de suspente est la hauteur de plénum.
 */
final class CalculateurSuspente implements CalculateurCouche
{
    public const int PAS_DEFAUT = 1200;
    public const int PLENUM_DEFAUT = 200;

    public function code(): string
    {
        return 'attache.suspentes';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $pas = $contexte->pasSuspente ?? self::PAS_DEFAUT;
        $nombre = 0;
        foreach (FourruresPlafond::longueurs($couche->surface, $contexte->directionOssature, $contexte->entraxeRetenu ?? CalculateurOssaturePlafond::ENTRAXE_DEFAUT) as $longueur) {
            $nombre += max(1, (int) ceil($longueur / $pas));
        }

        return new ResultatCouche([
            LigneBesoin::nombre('suspentes', $couche->emplacement->code, 'suspente', $nombre, ['longueur_mm' => $couche->parametreEntier('hauteur_plenum', self::PLENUM_DEFAUT)], $couche->materiau),
        ], $contexte);
    }
}
