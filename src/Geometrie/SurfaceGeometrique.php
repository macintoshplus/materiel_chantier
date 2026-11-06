<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Objet géométrique d'une surface : plan + contour identifié + ouvertures (dans le repère `(u, v)`).
 */
final readonly class SurfaceGeometrique
{
    /**
     * @param list<Polygone> $ouvertures
     */
    public function __construct(public Plan $plan, public ContourIdentifie $contour, public array $ouvertures = [])
    {
    }

    /**
     * @param list<Polygone> $ouvertures
     */
    public function avecOuvertures(array $ouvertures): self
    {
        return new self($this->plan, $this->contour, $ouvertures);
    }

    public function polygone(): Polygone
    {
        return $this->contour->polygone();
    }

    /**
     * Longueur réelle (mm, mesurée en 3D) de l'arête entre deux sommets.
     */
    public function longueurAreteReelle(string $debut, string $fin): int
    {
        return $this->plan->longueurReelle($this->contour->segment($debut, $fin));
    }

    public function pointPiece(string $id): Point3D
    {
        return $this->plan->versPiece($this->contour->point($id));
    }

    /**
     * Segment au sol (projection horizontale) de l'arête basse d'une surface verticale : de `u` minimal à `u` maximal.
     */
    public function segmentAuSol(): Segment
    {
        $boite = $this->polygone()->boiteEnglobante();
        $a = $this->plan->versPiece(new Point2D($boite->minX, $boite->minY));
        $b = $this->plan->versPiece(new Point2D($boite->maxX, $boite->minY));

        return new Segment(new Point2D($a->x, $a->y), new Point2D($b->x, $b->y));
    }

    /**
     * Éléments d'ossature : une droite tous les `pas` mm, la première sur le bord de la surface ; chaque élément
     * est l'intersection de la droite avec le polygone, ouvertures retranchées. Les longueurs sont celles du
     * repère de la surface, donc réelles (suivent la pente d'un pan). Les segments sont dans le repère `(u, v)`.
     *
     * @return list<Segment>
     */
    public function coupes(Direction $direction, int $pas): array
    {
        if ($pas <= 0) {
            throw new \InvalidArgumentException('The spacing must be positive.');
        }
        $vertical = Direction::Verticale === $direction;
        $polygone = $this->polygone();
        $boite = $polygone->boiteEnglobante();
        $min = $vertical ? $boite->minX : $boite->minY;
        $max = $vertical ? $boite->maxX : $boite->maxY;
        $segments = [];
        for ($c = $min; $c <= $max; $c += $pas) {
            $intervalles = self::intervalles($polygone, $vertical, $c, $min, $max);
            foreach ($this->ouvertures as $ouverture) {
                $b = $ouverture->boiteEnglobante();
                $debut = $vertical ? $b->minX : $b->minY;
                $fin = $vertical ? $b->maxX : $b->maxY;
                if ($c <= $debut || $c >= $fin) {
                    continue;
                }
                $intervalles = self::soustraire($intervalles, self::intervalles($ouverture, $vertical, $c, $debut, $fin));
            }
            foreach ($intervalles as [$a, $b]) {
                $segments[] = $vertical
                    ? new Segment(new Point2D($c, $a), new Point2D($c, $b))
                    : new Segment(new Point2D($a, $c), new Point2D($b, $c));
            }
        }

        return $segments;
    }

    /**
     * Intervalles (sur l'autre axe) de la droite `c` à l'intérieur du polygone. Règle demi-ouverte pour les
     * sommets sur la droite, fermée du côté du bord maximal.
     *
     * @return list<array{int, int}>
     */
    private static function intervalles(Polygone $polygone, bool $vertical, int $c, int $min, int $max): array
    {
        $valeurs = [];
        foreach ($polygone->aretes() as $arete) {
            [$a1, $b1] = $vertical ? [$arete->debut->x, $arete->debut->y] : [$arete->debut->y, $arete->debut->x];
            [$a2, $b2] = $vertical ? [$arete->fin->x, $arete->fin->y] : [$arete->fin->y, $arete->fin->x];
            if ($a1 === $a2) {
                continue;
            }
            [$lo, $hi] = $a1 < $a2 ? [$a1, $a2] : [$a2, $a1];
            $traverse = $c === $max && $max > $min ? ($lo < $c && $c <= $hi) : ($lo <= $c && $c < $hi);
            if ($traverse) {
                $valeurs[] = Tolerance::arrondir($b1 + ($c - $a1) * ($b2 - $b1) / ($a2 - $a1));
            }
        }
        sort($valeurs);
        $intervalles = [];
        for ($i = 0; $i + 1 < \count($valeurs); $i += 2) {
            if ($valeurs[$i + 1] > $valeurs[$i]) {
                $intervalles[] = [$valeurs[$i], $valeurs[$i + 1]];
            }
        }

        return $intervalles;
    }

    /**
     * @param list<array{int, int}> $intervalles
     * @param list<array{int, int}> $retrait
     *
     * @return list<array{int, int}>
     */
    private static function soustraire(array $intervalles, array $retrait): array
    {
        foreach ($retrait as [$r1, $r2]) {
            $resultat = [];
            foreach ($intervalles as [$a, $b]) {
                if ($r2 <= $a || $r1 >= $b) {
                    $resultat[] = [$a, $b];
                    continue;
                }
                if ($r1 > $a) {
                    $resultat[] = [$a, $r1];
                }
                if ($r2 < $b) {
                    $resultat[] = [$r2, $b];
                }
            }
            $intervalles = $resultat;
        }

        return $intervalles;
    }
}
