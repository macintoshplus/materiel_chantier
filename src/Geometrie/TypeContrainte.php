<?php

declare(strict_types=1);

namespace App\Geometrie;

enum TypeContrainte: string
{
    /** Sommet à une position absolue (u, v). */
    case Fixe = 'fixe';
    /** Distance depuis un sommet, dans une direction d'axe (u, -u, v, -v). */
    case Distance = 'distance';
    /** Distance depuis un sommet, avec un angle entre l'arête de référence et la nouvelle arête (dixièmes de degré). */
    case Angle = 'angle';
    /** Vérification : la distance entre deux sommets déjà placés vaut la valeur ; ne place aucun sommet. */
    case Controle = 'controle';
}
