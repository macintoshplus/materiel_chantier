<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Résout les contraintes de sommets en un polygone `(u, v)` en mm entiers, avec identifiants stables.
 * Les sommets sont créés dans l'ordre des contraintes (anti-horaire attendu). Chaque erreur désigne le sommet fautif.
 */
final class ConstructeurSurface
{
    private const DIRECTIONS = ['u' => [1, 0], '-u' => [-1, 0], 'v' => [0, 1], '-v' => [0, -1]];

    /**
     * @param list<ContrainteSommet> $contraintes
     *
     * @throws ErreurGeometrique constraint_* (sur-définition, référence inconnue, valeur invalide, incohérence, contour dégénéré)
     */
    public function construire(array $contraintes): ContourIdentifie
    {
        /** @var array<string, Point2D> $points */
        $points = [];
        $ordre = [];
        foreach ($contraintes as $c) {
            if (TypeContrainte::Controle === $c->type) {
                $this->verifier($c, $points);
                continue;
            }
            if (isset($points[$c->id])) {
                throw new ErreurGeometrique('constraint_duplicate_vertex', ['vertex' => $c->id], $c->id);
            }
            $points[$c->id] = $this->placer($c, $points);
            $ordre[] = $c->id;
        }
        if (\count($ordre) < 3) {
            throw new ErreurGeometrique('constraint_too_few_vertices');
        }
        $contour = new ContourIdentifie(array_map(static fn (string $id): Sommet => new Sommet($id, $points[$id]), $ordre));
        if (!$contour->polygone()->estSimple()) {
            throw new ErreurGeometrique('constraint_degenerate');
        }

        return $contour;
    }

    /**
     * @param array<string, Point2D> $points
     */
    private function placer(ContrainteSommet $c, array $points): Point2D
    {
        if (TypeContrainte::Fixe === $c->type) {
            if (null === $c->u || null === $c->v) {
                throw new ErreurGeometrique('constraint_malformed', ['vertex' => $c->id], $c->id);
            }

            return new Point2D($c->u, $c->v);
        }
        $depuis = $this->reference($c, $c->depuis, $points);
        if (null === $c->distance || $c->distance <= 0) {
            throw new ErreurGeometrique('constraint_invalid_value', ['vertex' => $c->id], $c->id);
        }
        if (TypeContrainte::Distance === $c->type) {
            $dir = self::DIRECTIONS[$c->direction ?? ''] ?? null;
            if (null === $dir) {
                throw new ErreurGeometrique('constraint_malformed', ['vertex' => $c->id], $c->id);
            }

            return new Point2D($depuis->x + $dir[0] * $c->distance, $depuis->y + $dir[1] * $c->distance);
        }
        if (null === $c->angle || $c->angle <= 0 || $c->angle >= 3600) {
            throw new ErreurGeometrique('constraint_invalid_value', ['vertex' => $c->id], $c->id);
        }
        if (null === $c->reference) {
            $theta = deg2rad($c->angle / 10);
        } else {
            $ref = $this->reference($c, $c->reference, $points);
            if ($ref->egal($depuis)) {
                throw new ErreurGeometrique('constraint_invalid_value', ['vertex' => $c->id], $c->id);
            }
            // direction de l'arête (référence -> depuis), tournée de (180° - angle) vers la gauche
            $theta = atan2($depuis->y - $ref->y, $depuis->x - $ref->x) + deg2rad(180 - $c->angle / 10);
        }

        return new Point2D(
            Tolerance::arrondir($depuis->x + $c->distance * cos($theta)),
            Tolerance::arrondir($depuis->y + $c->distance * sin($theta)),
        );
    }

    /**
     * @param array<string, Point2D> $points
     */
    private function verifier(ContrainteSommet $c, array $points): void
    {
        $a = $this->reference($c, $c->depuis, $points);
        $b = $this->reference($c, $c->vers, $points);
        if (null === $c->distance) {
            throw new ErreurGeometrique('constraint_malformed', ['vertex' => $c->id], $c->id);
        }
        $reel = $a->distanceA($b);
        if (!Tolerance::egal($reel, $c->distance)) {
            throw new ErreurGeometrique('constraint_inconsistent', ['from' => $c->depuis, 'to' => $c->vers, 'actual' => $reel, 'expected' => $c->distance], $c->id);
        }
    }

    /**
     * @param array<string, Point2D> $points
     */
    private function reference(ContrainteSommet $c, ?string $id, array $points): Point2D
    {
        if (null === $id) {
            throw new ErreurGeometrique('constraint_malformed', ['vertex' => $c->id], $c->id);
        }

        return $points[$id] ?? throw new ErreurGeometrique('constraint_unknown_reference', ['vertex' => $c->id, 'reference' => $id], $c->id);
    }
}
