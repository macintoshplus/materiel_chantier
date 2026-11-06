<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Étendue d'une agrégation : un achat unique pour tout le projet, un par pièce ou un par surface (ou cloison).
 */
enum PorteeAgregation: string
{
    case Projet = 'projet';
    case Piece = 'piece';
    case Surface = 'surface';
}
