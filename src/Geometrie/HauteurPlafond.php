<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Hauteur du plafond de la pièce en un point du plan : abstraction qui permet de générer les faces d'une cloison
 * sous un plafond plat, à pans ou à pignons sans cas particulier.
 */
interface HauteurPlafond
{
    /**
     * Cote (mm) du plafond à la verticale du point ; null si aucun plafond n'est défini à cet endroit.
     */
    public function hauteurA(Point2D $point): ?int;

    /**
     * Points d'un segment où la pente du plafond change (faîtage, noue), dans l'ordre du segment, extrémités exclues.
     *
     * @return list<Point2D>
     */
    public function ruptures(Segment $segment): array;

    /**
     * Clé de la surface de plafond au-dessus du point (identifiant opaque du pan), null si aucune.
     */
    public function cleA(Point2D $point): ?string;
}
