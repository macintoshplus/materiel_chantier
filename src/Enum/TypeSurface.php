<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Indicatif : un mur, un pan incliné et un plafond horizontal sont le même objet.
 */
enum TypeSurface: string
{
    case Mur = 'mur';
    case PlafondPan = 'plafond_pan';
    case Sol = 'sol';
    case CloisonFace = 'cloison_face';
}
