<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

/**
 * Stratégie de choix parmi les configurations possibles.
 */
enum StrategieConfiguration: string
{
    case EconomeMateriel = 'econome_materiel';
    case EconomeCout = 'econome_cout';
    case Imposee = 'imposee';
}
