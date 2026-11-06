<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Famille d'un équipement : seule l'électricité est calculée pour l'instant, les autres sont réservées aux étapes
 * suivantes (même entité, mêmes écrans).
 */
enum FamilleEquipement: string
{
    case Electrique = 'electrique';
    case Plomberie = 'plomberie';
    case Ventilation = 'ventilation';
}
