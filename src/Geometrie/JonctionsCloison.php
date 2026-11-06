<?php

declare(strict_types=1);

namespace App\Geometrie;

use App\Enum\CoteCloison;
use App\Enum\TypeAncrage;
use App\Enum\TypeJonction;

/**
 * Jonctions d'une cloison : sol, plafond (un par pan survolé) et ancrages (mur, cloison, doublage). Les jonctions
 * de cloison s'appuient sur une portion d'arête de la cible : aucune égalité de longueurs n'est exigée.
 */
final class JonctionsCloison
{
    /**
     * @return list<JonctionCloisonDecrite>
     */
    public function decrire(FacesCloison $faces, TraceCloison $trace, HauteurPlafond $plafond, Ancrage $debut, Ancrage $fin): array
    {
        $segment = $trace->segment();
        $bornes = [$trace->debut, ...$plafond->ruptures($segment), $trace->fin];
        $jonctions = [];
        foreach ([CoteCloison::A, CoteCloison::B] as $cote) {
            $face = CoteCloison::A === $cote ? $faces->a : $faces->b;
            $jonctions[] = new JonctionCloisonDecrite(TypeJonction::CloisonSurSol, $cote, 'db', 'fb');

            // arêtes hautes : consécutives parmi dh, r1h…, fh
            $hauts = array_values(array_filter($face->contour->ids(), static fn (string $id): bool => str_ends_with($id, 'h')));
            $ordre = ['dh', ...array_filter($hauts, static fn (string $id): bool => 'dh' !== $id && 'fh' !== $id), 'fh'];
            usort($ordre, static fn (string $x, string $y): int => self::rang($x) <=> self::rang($y));
            for ($i = 0; $i < \count($ordre) - 1; ++$i) {
                $milieu = new Point2D(
                    intdiv($bornes[$i]->x + $bornes[$i + 1]->x, 2),
                    intdiv($bornes[$i]->y + $bornes[$i + 1]->y, 2),
                );
                $cle = $plafond->cleA($milieu);
                if (null !== $cle) {
                    $jonctions[] = new JonctionCloisonDecrite(TypeJonction::CloisonSousPlafond, $cote, $ordre[$i], $ordre[$i + 1], $cle);
                }
            }

            foreach ([['db', 'dh', $debut], ['fb', 'fh', $fin]] as [$bas, $haut, $ancrage]) {
                $j = $this->ancrage($cote, $bas, $haut, $ancrage, $faces->epaisseur);
                if (null !== $j) {
                    $jonctions[] = $j;
                }
            }
        }

        return $jonctions;
    }

    private static function rang(string $id): int
    {
        return match (true) {
            'dh' === $id => 0,
            'fh' === $id => PHP_INT_MAX,
            default => (int) substr($id, 1),
        };
    }

    private function ancrage(CoteCloison $cote, string $bas, string $haut, Ancrage $ancrage, int $epaisseur): ?JonctionCloisonDecrite
    {
        $cible = $ancrage->cible;
        if (null === $cible || TypeAncrage::Libre === $ancrage->type) {
            return null;
        }
        $type = match ($ancrage->type) {
            TypeAncrage::Mur => TypeJonction::CloisonSurMur,
            TypeAncrage::Cloison => TypeJonction::CloisonSurCloison,
            TypeAncrage::Doublage => TypeJonction::CloisonSurDoublage,
        };
        $position = $ancrage->position ?? 0;
        $longueur = $cible->axe->longueur();

        return new JonctionCloisonDecrite(
            $type, $cote, $bas, $haut, $cible->cle, $cible->idDebut, $cible->idFin,
            max(0, $position - intdiv($epaisseur, 2)),
            min($longueur, $position + intdiv($epaisseur, 2)),
        );
    }
}
