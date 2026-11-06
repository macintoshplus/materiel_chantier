<?php

declare(strict_types=1);

namespace App\Import;

use App\Geometrie\Avertissement;
use App\Geometrie\Point2D;

/**
 * Résultat du tracé de côtés et d'angles : `trace` (polyligne telle que saisie, pour l'aperçu, ouverte si le
 * contour ne se referme pas), écart de fermeture en mm, et `contour` (polygone fermé, antihoraire) si l'écart est
 * sous le seuil de fermeture automatique.
 */
final readonly class ResultatCotes
{
    /**
     * @param list<Point2D>      $trace
     * @param list<Point2D>|null $contour
     */
    public function __construct(
        public array $trace,
        public float $ecart,
        public ?array $contour,
        public ?Avertissement $avertissement = null,
    ) {
    }

    public function ferme(): bool
    {
        return null !== $this->contour;
    }
}
