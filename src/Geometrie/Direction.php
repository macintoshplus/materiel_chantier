<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Direction des éléments d'ossature dans le repère (u, v) de la surface.
 * Verticale : éléments le long de `v`, espacés le long de `u` (montants, fourrures perpendiculaires à l'égout).
 * Horizontale : éléments le long de `u`, espacés le long de `v` (rails intermédiaires, lisses).
 */
enum Direction: string
{
    case Verticale = 'verticale';
    case Horizontale = 'horizontale';

    public function perpendiculaire(): self
    {
        return self::Verticale === $this ? self::Horizontale : self::Verticale;
    }
}
