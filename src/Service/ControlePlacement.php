<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cloison;
use App\Entity\Surface;
use App\Enum\CoteCloison;
use App\Geometrie\Avertissement;
use App\Geometrie\OuvertureAPlacer;
use App\Geometrie\OuvertureCloison;
use App\Geometrie\PlacementSurSurface;

/**
 * Contrôles non bloquants du placement des ouvertures (sortie de la surface, chevauchement, distance à une arête).
 */
final class ControlePlacement
{
    /** Distance minimale (mm) entre une ouverture et une arête de la surface. */
    public const DISTANCE_MINIMALE_ARETE = 50;

    /**
     * @return list<Avertissement>
     */
    public function pourSurface(Surface $surface): array
    {
        $aPlacer = [];
        foreach ($surface->getOuvertures() as $ouverture) {
            $aPlacer[] = new OuvertureAPlacer($ouverture->libelle(), $ouverture->getForme());
        }

        return (new PlacementSurSurface())->verifier($surface->getContour()->polygone(), $aPlacer, self::DISTANCE_MINIMALE_ARETE);
    }

    /**
     * Contrôle sur la face B (les ouvertures y suivent le tracé) ; la face A est son image inversée.
     *
     * @return list<Avertissement>
     */
    public function pourCloison(Cloison $cloison): array
    {
        $faceB = null;
        foreach ($cloison->getFaces() as $face) {
            if (CoteCloison::B === $face->getCote()) {
                $faceB = $face;
            }
        }
        if (null === $faceB) {
            return [];
        }
        $longueur = $cloison->getTrace()->longueur();
        $aPlacer = [];
        foreach ($cloison->getOuvertures() as $ouverture) {
            $aPlacer[] = new OuvertureAPlacer($ouverture->libelle(), (new OuvertureCloison())->projeter($ouverture->getForme(), $longueur)->faceB);
        }

        return (new PlacementSurSurface())->verifier($faceB->getContour()->polygone(), $aPlacer, self::DISTANCE_MINIMALE_ARETE);
    }
}
