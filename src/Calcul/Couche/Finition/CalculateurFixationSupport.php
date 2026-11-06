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
 * Chevilles ou vis à béton : nombre = longueur de profilé fixé au support (rails ou lisses bas et haut publiés par
 * l'ossature) ÷ `pose.pas_mm` de l'article (ou `1000 / pose.nombre_par_m`), plus les fixations de périphérie : un
 * nombre `fixations_par_extremite` (1 par défaut) à chaque extrémité de profilé.
 */
final class CalculateurFixationSupport implements CalculateurCouche
{
    public function code(): string
    {
        return 'fixation.support';
    }

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche
    {
        $materiau = $couche->materiau ?? throw new \LogicException('The support fixing layer has no material.');
        $pas = $materiau->valeur('pose.pas_mm');
        if (!\is_int($pas)) {
            $parMetre = $materiau->valeur('pose.nombre_par_m');
            $pas = (\is_int($parMetre) || \is_float($parMetre)) && $parMetre > 0 ? (int) round(1000 / $parMetre) : 0;
        }
        if ($pas <= 0) {
            return new ResultatCouche([], $contexte, [], [new Avertissement('fixing_pitch_missing', ['order' => $couche->couche->ordre])]);
        }
        $longueur = array_sum($contexte->railsSupport);
        $peripherie = 2 * \count($contexte->railsSupport) * max(0, $couche->parametreEntier('fixations_par_extremite', 1));
        $nombre = Consommables::fixations($longueur, $pas) + $peripherie;

        return new ResultatCouche([
            LigneBesoin::nombre('fixations_support', $couche->emplacement->code, 'fixation_support', $nombre, ['pas_mm' => $pas, 'peripherie' => $peripherie], $materiau),
        ], $contexte);
    }
}
