<?php

declare(strict_types=1);

namespace App\Import;

use App\Geometrie\Avertissement;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;

/**
 * Contour du plan importé, en mm. Un contour rejeté (courbe, croisement) reste listé avec son motif pour être montré
 * dans l'aperçu, mais ne peut pas être choisi.
 */
final readonly class ContourImporte
{
    /**
     * @param list<Point2D> $points         sommets en mm (pour un contour rejeté pour courbe : les cordes)
     * @param int           $ecartChainage  plus grand jeu (mm) refermé lors du chaînage de lignes
     * @param string        $origine        `polyligne` ou `lignes`
     */
    public function __construct(
        public array $points,
        public string $calque,
        public bool $ferme = true,
        public ?Avertissement $rejet = null,
        public int $ecartChainage = 0,
        public string $origine = 'polyligne',
    ) {
    }

    public function selectionnable(): bool
    {
        return $this->ferme && null === $this->rejet && \count($this->points) >= 3;
    }

    public function polygone(): Polygone
    {
        return new Polygone($this->points);
    }
}
