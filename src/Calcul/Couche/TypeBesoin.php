<?php

declare(strict_types=1);

namespace App\Calcul\Couche;

/**
 * Nature d'un besoin brut : pièces linéaires à découper, nombre d'unités, surface (mm²) ou longueur cumulée (mm).
 */
enum TypeBesoin: string
{
    case Pieces = 'pieces';
    case Nombre = 'nombre';
    case Surface = 'surface';
    case Longueur = 'longueur';
}
