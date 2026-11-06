<?php

declare(strict_types=1);

namespace App\Geometrie;

use App\Enum\TypeAncrage;

/**
 * Propose l'ancrage d'une extrémité de cloison : la cible la plus proche (mur, doublage ou autre cloison) dans
 * la tolérance, sinon `libre`. Pour une cloison, la distance est mesurée à la face (axe moins demi-épaisseur).
 */
final class DetecteurAncrages
{
    public const TOLERANCE_DEFAUT = 20;

    private const PRIORITE = [TypeAncrage::Mur->value => 0, TypeAncrage::Doublage->value => 1, TypeAncrage::Cloison->value => 2];

    /**
     * @param list<CibleAncrage> $cibles
     */
    public function proposer(Point2D $extremite, array $cibles, int $tolerance = self::TOLERANCE_DEFAUT): Ancrage
    {
        $retenue = null;
        $distanceRetenue = INF;
        foreach ($cibles as $cible) {
            $distance = max(0.0, $cible->axe->distanceAuPoint($extremite) - $cible->epaisseur / 2);
            if ($distance > $tolerance) {
                continue;
            }
            if (null === $retenue
                || $distance < $distanceRetenue - Tolerance::MM
                || (abs($distance - $distanceRetenue) <= Tolerance::MM && (self::PRIORITE[$cible->type->value] ?? 9) < (self::PRIORITE[$retenue->type->value] ?? 9))) {
                $retenue = $cible;
                $distanceRetenue = $distance;
            }
        }
        if (null === $retenue) {
            return Ancrage::libre();
        }

        return new Ancrage($retenue->type, $retenue, $retenue->axe->positionDe($extremite));
    }
}
