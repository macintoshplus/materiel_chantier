<?php

declare(strict_types=1);

namespace App\Apercu;

use App\Entity\Piece;
use App\Entity\Surface;
use App\Geometrie\BoiteEnglobante;
use App\Geometrie\GenerateurFacesCloison;
use App\Geometrie\Point2D;
use App\Service\OrdreSurfaces;

/**
 * Aperçu SVG d'une pièce vue de dessus : sol, murs nommés (cliquables), ouvertures sur les murs et cloisons en trait
 * épais nommées. Une unité SVG = 1 mm, `y` inversé (`y = -y` de la pièce) : un clic sur le plan donne `(x, -y)`.
 * Le modèle de vue est rendu par Twig ; il n'est calculé qu'à partir de l'état enregistré.
 *
 * @phpstan-type VueOuvertureMur array{id: int|null, libelle: string, x1: int, y1: int, x2: int, y2: int, porte: bool}
 * @phpstan-type VueMur array{id: int|null, nom: string, x1: int, y1: int, x2: int, y2: int, lx: int, ly: int, ouvertures: list<VueOuvertureMur>}
 * @phpstan-type VueCloison array{id: int|null, nom: string, x1: int, y1: int, x2: int, y2: int, epaisseur: int, lx: int, ly: int, ouvertures: list<VueOuvertureMur>}
 * @phpstan-type VuePiece array{vide: bool, viewBox: string, sol: string, murs: list<VueMur>, cloisons: list<VueCloison>, police: int, trait: int}
 */
final class ApercuPiece
{
    /**
     * @return VuePiece
     */
    public function construire(Piece $piece): array
    {
        $sol = $piece->getPolygoneSol();
        if ([] === $sol->points) {
            return ['vide' => true, 'viewBox' => '0 0 1000 1000', 'sol' => '', 'murs' => [], 'cloisons' => [], 'police' => 40, 'trait' => 8];
        }
        $boite = $sol->boiteEnglobante();
        $taille = max($boite->largeur(), $boite->hauteur(), 1000);
        $police = max(40, intdiv($taille, 30));
        $decalageEtiquette = 2 * $police;
        $marge = intdiv($taille, 10) + 3 * $police;
        $contour = $piece->getSol()?->getContour();

        $murs = [];
        foreach (OrdreSurfaces::murs($piece) as $mur) {
            $arete = $mur->getAreteSol();
            if (null === $contour || null === $arete || !$contour->a($arete['debut']) || !$contour->a($arete['fin'])) {
                continue;
            }
            $a = $contour->point($arete['debut']);
            $b = $contour->point($arete['fin']);
            [$lx, $ly] = $this->etiquette($a, $b, $decalageEtiquette, true);
            $murs[] = [
                'id' => $mur->getId(),
                'nom' => $mur->getNom(),
                'x1' => $a->x,
                'y1' => -$a->y,
                'x2' => $b->x,
                'y2' => -$b->y,
                'lx' => $lx,
                'ly' => $ly,
                'ouvertures' => $this->ouverturesDuMur($mur),
            ];
        }

        $cloisons = [];
        $minX = $boite->minX;
        $maxX = $boite->maxX;
        $minY = $boite->minY;
        $maxY = $boite->maxY;
        foreach ($piece->getCloisons() as $cloison) {
            $trace = $cloison->getTrace();
            [$lx, $ly] = $this->etiquette($trace->debut, $trace->fin, $decalageEtiquette, false);
            $ouvertures = [];
            $longueur = max(1, $trace->longueur());
            foreach ($cloison->getOuvertures() as $o) {
                $ouvertures[] = [
                    'id' => $o->getId(),
                    'libelle' => $o->libelle(),
                    'x1' => $trace->debut->x + $this->part($trace->fin->x - $trace->debut->x, $o->getU(), $longueur),
                    'y1' => -($trace->debut->y + $this->part($trace->fin->y - $trace->debut->y, $o->getU(), $longueur)),
                    'x2' => $trace->debut->x + $this->part($trace->fin->x - $trace->debut->x, $o->getU() + $o->getLargeur(), $longueur),
                    'y2' => -($trace->debut->y + $this->part($trace->fin->y - $trace->debut->y, $o->getU() + $o->getLargeur(), $longueur)),
                    'porte' => 'porte' === $o->getType(),
                ];
            }
            foreach ([$trace->debut, $trace->fin] as $p) {
                $minX = min($minX, $p->x);
                $maxX = max($maxX, $p->x);
                $minY = min($minY, $p->y);
                $maxY = max($maxY, $p->y);
            }
            $cloisons[] = [
                'id' => $cloison->getId(),
                'nom' => $cloison->getNom(),
                'x1' => $trace->debut->x,
                'y1' => -$trace->debut->y,
                'x2' => $trace->fin->x,
                'y2' => -$trace->fin->y,
                'epaisseur' => $cloison->getEpaisseur() ?? GenerateurFacesCloison::EPAISSEUR_PROVISOIRE,
                'lx' => $lx,
                'ly' => $ly,
                'ouvertures' => $ouvertures,
            ];
        }
        $etendue = new BoiteEnglobante($minX, $minY, $maxX, $maxY);

        return [
            'vide' => false,
            'viewBox' => sprintf('%d %d %d %d', $etendue->minX - $marge, -$etendue->maxY - $marge, $etendue->largeur() + 2 * $marge, $etendue->hauteur() + 2 * $marge),
            'sol' => implode(' ', array_map(static fn (Point2D $p): string => $p->x.','.(-$p->y), $sol->points)),
            'murs' => $murs,
            'cloisons' => $cloisons,
            'police' => $police,
            'trait' => max(10, intdiv($taille, 150)),
        ];
    }

    /**
     * Ouvertures d'un mur projetées sur son arête du sol.
     *
     * @return list<VueOuvertureMur>
     */
    private function ouverturesDuMur(Surface $mur): array
    {
        $plan = $mur->getPlan();
        $vues = [];
        foreach ($mur->getOuvertures() as $o) {
            $a = $plan->versPiece(new Point2D($o->getU(), 0));
            $b = $plan->versPiece(new Point2D($o->getU() + $o->getLargeur(), 0));
            $vues[] = ['id' => $o->getId(), 'libelle' => $o->libelle(), 'x1' => $a->x, 'y1' => -$a->y, 'x2' => $b->x, 'y2' => -$b->y, 'porte' => 'porte' === $o->getType()];
        }

        return $vues;
    }

    private function part(int $delta, int $position, int $longueur): int
    {
        return (int) round($delta * $position / $longueur);
    }

    /**
     * Position (coordonnées SVG) de l'étiquette : milieu de l'arête, décalé vers l'extérieur du sol (murs, sol
     * anti-horaire) ou du côté gauche du tracé (cloisons).
     *
     * @return array{int, int}
     */
    private function etiquette(Point2D $a, Point2D $b, int $decalage, bool $exterieur): array
    {
        $dx = $b->x - $a->x;
        $dy = $b->y - $a->y;
        $n = max(1.0, hypot($dx, $dy));
        // sens de parcours anti-horaire : l'extérieur est à droite (dy, -dx)
        $signe = $exterieur ? 1 : -1;
        $x = (int) round(($a->x + $b->x) / 2 + $signe * $dy / $n * $decalage);
        $y = (int) round(($a->y + $b->y) / 2 - $signe * $dx / $n * $decalage);

        return [$x, -$y];
    }
}
