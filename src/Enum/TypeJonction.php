<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeJonction: string
{
    case AngleSaillant = 'angle_saillant';
    case AngleRentrant = 'angle_rentrant';
    case Faitage = 'faitage';
    case Noue = 'noue';
    case Plat = 'plat';
    case Sabliere = 'sabliere';
    case CloisonSurMur = 'cloison_sur_mur';
    case CloisonSurCloison = 'cloison_sur_cloison';
    case CloisonSurDoublage = 'cloison_sur_doublage';
    case CloisonSurSol = 'cloison_sur_sol';
    case CloisonSousPlafond = 'cloison_sous_plafond';

    /**
     * Entre surfaces de même contour : les deux arêtes jointes ont la même longueur (± tolérance).
     * Une cloison s'appuie sur une portion d'arête, sans égalité.
     */
    public function exigeEgaliteLongueurs(): bool
    {
        return !str_starts_with($this->value, 'cloison_');
    }
}
