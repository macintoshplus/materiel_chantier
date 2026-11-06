<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Finition;

use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;
use App\Geometrie\Avertissement;

/**
 * Enduit, en kg (arrondi au kg supérieur ; l'article donne le poids du sac en `unites_par_lot`). Deux usages, selon
 * l'emplacement : `enduit_joint` consomme `consommation_kg_par_m2` par m² de parement ; `enduit_ratissage` (facultatif)
 * consomme `épaisseur × consommation_kg_par_m2_par_mm`, l'épaisseur (mm) étant celle de la couche. La surface de
 * parement est celle publiée par la peau visible (retours en tunnel compris), sinon la surface nette de la surface.
 */
final class CalculateurEnduit implements CalculateurCouche
{
    public const string JOINT = 'enduit_joint';
    public const string RATISSAGE = 'enduit_ratissage';

    public function code(): string
    {
        return 'enduit.plaques';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $materiau = $couche->materiau ?? throw new \LogicException('The plaster layer has no material.');
        $ratissage = self::RATISSAGE === $couche->emplacement->code;
        $surface = $contexte->surfaceParementMm2 > 0 ? $contexte->surfaceParementMm2 : $couche->surface->surfaceNette();
        $avertissements = [];
        $usage = $materiau->valeur('usage');
        if (\is_string($usage) && $usage !== ($ratissage ? 'ratissage' : 'joint')) {
            $avertissements[] = new Avertissement('plaster_usage_mismatch', ['order' => $couche->couche->ordre, 'usage' => $usage]);
        }

        $caracteristiques = [];
        if ($ratissage) {
            $epaisseur = $couche->couche->epaisseurOverride;
            if (null === $epaisseur || $epaisseur <= 0) {
                return new ResultatCouche([], $contexte, $avertissements, [new Avertissement('plaster_thickness_missing', ['order' => $couche->couche->ordre])]);
            }
            $parMm = Consommables::milliemes($materiau, 'consommation_kg_par_m2_par_mm');
            if (null === $parMm) {
                return new ResultatCouche([], $contexte, $avertissements, [new Avertissement('plaster_consumption_missing', ['order' => $couche->couche->ordre])]);
            }
            $grammes = $epaisseur * $parMm;
            $caracteristiques = ['epaisseur_mm' => $epaisseur];
        } else {
            $grammes = Consommables::milliemes($materiau, 'consommation_kg_par_m2');
            if (null === $grammes) {
                return new ResultatCouche([], $contexte, $avertissements, [new Avertissement('plaster_consumption_missing', ['order' => $couche->couche->ordre])]);
            }
        }

        $cle = $ratissage ? 'ratissage' : 'enduit_joint';

        return new ResultatCouche(
            [LigneBesoin::nombre($cle.'_'.$couche->couche->ordre, $couche->emplacement->code, 'enduit', Consommables::kilos($surface, $grammes), $caracteristiques, $materiau)],
            $contexte,
            $avertissements,
        );
    }
}
