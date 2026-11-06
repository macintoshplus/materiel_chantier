<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Validité du placement d'ouvertures (ou d'éléments) sur une surface : inclusion dans le polygone, absence de
 * chevauchement, distance minimale à une arête (désactivée si 0). Les arêtes basses sont ignorées pour la
 * distance : une porte repose sur le sol.
 */
final class PlacementSurSurface
{
    /**
     * @param list<OuvertureAPlacer> $ouvertures
     *
     * @return list<Avertissement>
     */
    public function verifier(Polygone $surface, array $ouvertures, int $distanceMinimale = 0): array
    {
        $avertissements = [];
        $bas = $surface->boiteEnglobante()->minY;
        foreach ($ouvertures as $i => $o) {
            if (!$surface->contientPolygone($o->forme)) {
                $avertissements[] = new Avertissement('opening_outside_surface', ['opening' => $o->nom]);
                continue;
            }
            if ($distanceMinimale > 0) {
                $distance = $this->distanceAuxAretes($surface, $o->forme, $bas);
                if (null !== $distance && $distance < $distanceMinimale - Tolerance::MM) {
                    $avertissements[] = new Avertissement('opening_too_close_to_edge', [
                        'opening' => $o->nom, 'distance' => Tolerance::arrondir($distance), 'minimum' => $distanceMinimale,
                    ]);
                }
            }
            foreach ($ouvertures as $j => $autre) {
                if ($j > $i && $o->forme->chevauche($autre->forme)) {
                    $avertissements[] = new Avertissement('opening_overlap', ['opening' => $o->nom, 'other' => $autre->nom]);
                }
            }
        }

        return $avertissements;
    }

    private function distanceAuxAretes(Polygone $surface, Polygone $forme, int $bas): ?float
    {
        $min = null;
        foreach ($surface->aretes() as $arete) {
            if ($arete->debut->y === $bas && $arete->fin->y === $bas) {
                continue;
            }
            foreach ($forme->points as $p) {
                $min = min($min ?? INF, $arete->distanceAuPoint($p));
            }
            foreach ($forme->aretes() as $f) {
                $min = min($min ?? INF, $f->distanceAuPoint($arete->debut), $f->distanceAuPoint($arete->fin));
            }
        }

        return $min;
    }
}
