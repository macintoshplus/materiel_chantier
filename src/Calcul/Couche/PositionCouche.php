<?php

declare(strict_types=1);

namespace App\Calcul\Couche;

/**
 * Position d'une couche par rapport à l'ossature : dans la cavité, devant l'ossature, ou posée sur l'ossature (parement).
 */
enum PositionCouche: string
{
    case EntreOssature = 'entre_ossature';
    case Continue = 'continue';
    case SurOssature = 'sur_ossature';
}
