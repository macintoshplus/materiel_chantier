<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Piece;
use App\Entity\Surface;
use App\Enum\TypeSurface;

/**
 * Ordre d'affichage des surfaces d'une pièce : les murs suivent les arêtes du sol, les pans leur ordre de création.
 */
final class OrdreSurfaces
{
    /**
     * @return list<Surface>
     */
    public static function murs(Piece $piece): array
    {
        $murs = $piece->getMurs();
        $sol = $piece->getSol();
        if (null === $sol) {
            return $murs;
        }
        $ordonnes = [];
        foreach ($sol->getContour()->aretes() as [$debut]) {
            foreach ($murs as $i => $mur) {
                if (($mur->getAreteSol()['debut'] ?? null) === $debut) {
                    $ordonnes[] = $mur;
                    unset($murs[$i]);
                }
            }
        }

        return [...$ordonnes, ...array_values($murs)];
    }

    /**
     * Surfaces de la même famille que `$surface` (murs ou pans), dans l'ordre de navigation.
     *
     * @return list<Surface>
     */
    public static function famille(Surface $surface): array
    {
        $piece = $surface->getPiece();
        if (TypeSurface::Mur === $surface->getType()) {
            return self::murs($piece);
        }
        if (TypeSurface::PlafondPan === $surface->getType()) {
            return $piece->getPans();
        }

        return [$surface];
    }

    /**
     * @return array{precedent: Surface|null, suivant: Surface|null} navigation circulaire ; null si la surface est seule
     */
    public static function voisins(Surface $surface): array
    {
        $liste = self::famille($surface);
        $n = \count($liste);
        $i = array_search($surface, $liste, true);
        if (false === $i || $n < 2) {
            return ['precedent' => null, 'suivant' => null];
        }

        return ['precedent' => $liste[($i + $n - 1) % $n], 'suivant' => $liste[($i + 1) % $n]];
    }
}
