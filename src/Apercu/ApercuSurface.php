<?php

declare(strict_types=1);

namespace App\Apercu;

use App\Entity\Cloison;
use App\Entity\Ouverture;
use App\Entity\Surface;
use App\Enum\CoteCloison;
use App\Enum\TypePose;
use App\Geometrie\BoiteEnglobante;
use App\Geometrie\ContourIdentifie;
use App\Geometrie\OuvertureCloison;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;

/**
 * Aperçu SVG d'une surface (mur, pan, sol, face de cloison) dans son repère `(u, v)` : contour, sommets nommés,
 * ouvertures et, en pose tunnel, bandes de retour de parement. Une unité SVG = 1 mm ; l'axe `v` est inversé
 * (`y = -v`) pour que le haut de la surface soit en haut de l'image. Le modèle de vue est rendu par Twig.
 *
 * @phpstan-type VueOuverture array{id: int|null, libelle: string, points: string, x: int, y: int, largeur: int, hauteur: int, tunnel: bool, porte: bool, retours: list<array{x: int, y: int, largeur: int, hauteur: int}>}
 * @phpstan-type VueSommet array{id: string, x: int, y: int}
 * @phpstan-type VueSurface array{viewBox: string, contour: string, sommets: list<VueSommet>, ouvertures: list<VueOuverture>, police: int, trait: int}
 */
final class ApercuSurface
{
    /** Profondeur de base (mm) des bandes de retour dessinées, augmentée du refoncement ; schématique. */
    public const RETOUR_BASE = 60;

    /**
     * @return VueSurface
     */
    public function pourSurface(Surface $surface): array
    {
        $ouvertures = [];
        foreach ($surface->getOuvertures() as $ouverture) {
            $ouvertures[] = ['ouverture' => $ouverture, 'forme' => $ouverture->getForme()];
        }

        return $this->construire($surface->getContour(), $ouvertures);
    }

    /**
     * Une face de cloison avec les ouvertures de la cloison : la face B suit le tracé, la face A est inversée.
     *
     * @return VueSurface
     */
    public function pourCloison(Cloison $cloison, CoteCloison $cote): array
    {
        $face = null;
        foreach ($cloison->getFaces() as $candidate) {
            if ($cote === $candidate->getCote()) {
                $face = $candidate;
            }
        }
        if (null === $face) {
            return $this->construire(new ContourIdentifie([]), []);
        }
        $longueur = $cloison->getTrace()->longueur();
        $ouvertures = [];
        foreach ($cloison->getOuvertures() as $ouverture) {
            $projection = (new OuvertureCloison())->projeter($ouverture->getForme(), $longueur);
            $ouvertures[] = ['ouverture' => $ouverture, 'forme' => CoteCloison::A === $cote ? $projection->faceA : $projection->faceB];
        }

        return $this->construire($face->getContour(), $ouvertures);
    }

    /**
     * @param list<array{ouverture: Ouverture, forme: Polygone}> $ouvertures formes déjà exprimées dans le repère de la surface
     *
     * @return VueSurface
     */
    public function construire(ContourIdentifie $contour, array $ouvertures): array
    {
        $points = $contour->polygone()->points;
        $tous = $points;
        foreach ($ouvertures as $o) {
            array_push($tous, ...$o['forme']->points);
        }
        $boite = [] === $tous ? new BoiteEnglobante(0, 0, 1000, 1000) : BoiteEnglobante::depuisPoints($tous);
        $taille = max($boite->largeur(), $boite->hauteur(), 1000);
        $police = max(30, intdiv($taille, 40));
        $marge = intdiv($taille, 12) + 2 * $police;

        $vues = [];
        foreach ($ouvertures as $o) {
            $vues[] = $this->ouverture($o['ouverture'], $o['forme']);
        }

        return [
            'viewBox' => sprintf('%d %d %d %d', $boite->minX - $marge, -$boite->maxY - $marge, $boite->largeur() + 2 * $marge, $boite->hauteur() + 2 * $marge),
            'contour' => self::points($points),
            'sommets' => array_map(static fn ($s): array => ['id' => $s->id, 'x' => $s->point->x, 'y' => -$s->point->y], $contour->sommets),
            'ouvertures' => $vues,
            'police' => $police,
            'trait' => max(8, intdiv($taille, 250)),
        ];
    }

    /**
     * @return VueOuverture
     */
    private function ouverture(Ouverture $ouverture, Polygone $forme): array
    {
        $boite = BoiteEnglobante::depuisPoints($forme->points);
        $largeur = $boite->largeur();
        $hauteur = $boite->hauteur();
        $tunnel = TypePose::Tunnel === $ouverture->getTypePose();
        $porte = 'porte' === $ouverture->getType();
        $retours = [];
        if ($tunnel) {
            $e = max(1, min(self::RETOUR_BASE + $ouverture->getRefoncement(), intdiv(min($largeur, $hauteur), 3)));
            $x = $boite->minX;
            $y = -$boite->maxY;
            $retours[] = ['x' => $x, 'y' => $y, 'largeur' => $e, 'hauteur' => $hauteur];
            $retours[] = ['x' => $x + $largeur - $e, 'y' => $y, 'largeur' => $e, 'hauteur' => $hauteur];
            $retours[] = ['x' => $x, 'y' => $y, 'largeur' => $largeur, 'hauteur' => $e];
            if (!$porte) {
                $retours[] = ['x' => $x, 'y' => $y + $hauteur - $e, 'largeur' => $largeur, 'hauteur' => $e];
            }
        }

        return [
            'id' => $ouverture->getId(),
            'libelle' => $ouverture->libelle(),
            'points' => self::points($forme->points),
            'x' => $boite->minX,
            'y' => -$boite->maxY,
            'largeur' => $largeur,
            'hauteur' => $hauteur,
            'tunnel' => $tunnel,
            'porte' => $porte,
            'retours' => $retours,
        ];
    }

    /**
     * @param list<Point2D> $points
     */
    private static function points(array $points): string
    {
        return implode(' ', array_map(static fn (Point2D $p): string => $p->x.','.(-$p->y), $points));
    }
}
