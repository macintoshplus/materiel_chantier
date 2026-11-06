<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

use App\Calcul\Surface\SurfaceCalculable;
use App\Geometrie\Direction;

/**
 * Fourrures d'un plafond suspendu : une par position à l'entraxe, longueur réelle dans le repère de la surface
 * (suit la pente d'un pan), plus une fourrure au bord opposé si la largeur n'est pas un multiple de l'entraxe.
 */
final class FourruresPlafond
{
    /**
     * @return list<int> longueurs (mm) des fourrures
     */
    public static function longueurs(SurfaceCalculable $surface, Direction $direction, int $entraxe): array
    {
        $boite = $surface->boite();
        $vertical = Direction::Verticale === $direction;
        $min = $vertical ? $boite->minX : $boite->minY;
        $max = $vertical ? $boite->maxX : $boite->maxY;
        $longueurs = [];
        foreach ($surface->coupes($direction, $entraxe) as $segment) {
            $longueurs[] = $segment->longueur();
        }
        $etendue = $max - $min;
        if ($etendue > 0 && 0 !== $etendue % $entraxe) {
            foreach ($surface->coupes($direction, $etendue) as $segment) {
                if (($vertical ? $segment->debut->x : $segment->debut->y) === $max) {
                    $longueurs[] = $segment->longueur();
                }
            }
        }

        return $longueurs;
    }
}
