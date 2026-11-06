<?php

declare(strict_types=1);

namespace App\Calcul\Couche\Finition;

use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Couche\CoucheContexte;
use App\Calcul\Couche\ContexteCouches;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\ResultatCouche;
use App\Calcul\Couche\TypeBesoin;

/**
 * Bande de joint des angles de retour de parement (à placer après le parement) : bande `armee` sur les angles
 * sortants (retour contre parement), bande `plat` ou `papier` sur les angles rentrants (retour contre retour).
 * Rouleaux de `longueur_rouleau` quand l'article la donne, sinon longueur en mm. Sans retour, aucun besoin.
 */
final class CalculateurBandeJoint implements CalculateurCouche
{
    public function code(): string
    {
        return 'bande.joint';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $materiau = $couche->materiau ?? throw new \LogicException('The joint tape layer has no material.');
        $sortant = 'armee' === $materiau->valeur('type');
        $longueur = $sortant ? $contexte->anglesSortantsMm : $contexte->anglesRentrantsMm;
        if ($longueur <= 0) {
            return new ResultatCouche([], $contexte);
        }
        $cle = ($sortant ? 'bande_angles_sortants_' : 'bande_angles_rentrants_').$couche->couche->ordre;
        $rouleau = $materiau->valeur('longueur_rouleau');
        if (\is_int($rouleau) && $rouleau > 0) {
            return new ResultatCouche([LigneBesoin::nombre($cle, $couche->emplacement->code, 'bande_joint', Consommables::rouleaux($longueur, 0, $rouleau), ['longueur_mm' => $longueur], $materiau)], $contexte);
        }

        return new ResultatCouche([new LigneBesoin($cle, $couche->emplacement->code, 'bande_joint', TypeBesoin::Longueur, $longueur, [], null, [], $materiau)], $contexte);
    }
}
