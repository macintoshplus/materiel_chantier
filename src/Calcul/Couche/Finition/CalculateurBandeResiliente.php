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
 * Bande résiliente sous les rails ou lisses au contact du support : rouleaux = `ceil(longueur × (1 + marge) /
 * longueur_rouleau)`. La marge est le paramètre `marge_bande_resiliente_pct` (0 par défaut : la marge de sécurité du
 * matériau est appliquée à l'agrégation, étape 05e).
 */
final class CalculateurBandeResiliente implements CalculateurCouche
{
    public function code(): string
    {
        return 'bande.resiliente';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $materiau = $couche->materiau ?? throw new \LogicException('The resilient strip layer has no material.');
        $rouleau = $materiau->valeur('longueur_rouleau');
        if (!\is_int($rouleau) || $rouleau <= 0) {
            return new ResultatCouche([], $contexte, [], [new Avertissement('roll_length_missing', ['order' => $couche->couche->ordre])]);
        }
        $longueur = array_sum($contexte->railsSupport);
        $rouleaux = Consommables::rouleaux($longueur, $couche->parametreEntier('marge_bande_resiliente_pct', 0), $rouleau);

        return new ResultatCouche([
            LigneBesoin::nombre('bande_resiliente', $couche->emplacement->code, 'bande_resiliente', $rouleaux, ['longueur_mm' => $longueur], $materiau),
        ], $contexte);
    }
}
