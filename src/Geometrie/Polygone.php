<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Polygone plan, mm entiers. Les calculs d'incidence sont exacts en entiers ; la tolérance (1 mm) ne sert
 * qu'à décider qu'un point est « sur le bord ».
 */
final readonly class Polygone
{
    /**
     * @param list<Point2D> $points
     */
    public function __construct(public array $points)
    {
    }

    /**
     * @param list<array{x: int, y: int}> $points
     */
    public static function depuisTableaux(array $points): self
    {
        return new self(array_map(Point2D::depuisTableau(...), $points));
    }

    /**
     * @return list<array{x: int, y: int}>
     */
    public function versTableaux(): array
    {
        return array_map(static fn (Point2D $p): array => $p->versTableau(), $this->points);
    }

    /**
     * Deux fois l'aire signée (mm²), positive si le sens est anti-horaire (formule du lacet).
     */
    public function aireDoublee(): int
    {
        $somme = 0;
        $n = \count($this->points);
        for ($i = 0; $i < $n; ++$i) {
            $a = $this->points[$i];
            $b = $this->points[($i + 1) % $n];
            $somme += $a->x * $b->y - $b->x * $a->y;
        }

        return $somme;
    }

    /** Aire (mm²), arrondie. */
    public function aire(): int
    {
        return intdiv(abs($this->aireDoublee()) + 1, 2);
    }

    /** Périmètre (mm), arrondi. */
    public function perimetre(): int
    {
        $total = 0.0;
        foreach ($this->aretes() as $arete) {
            $total += hypot($arete->vecteur()->x, $arete->vecteur()->y);
        }

        return Tolerance::arrondir($total);
    }

    public function sensAntiHoraire(): bool
    {
        return $this->aireDoublee() > 0;
    }

    /**
     * @return list<Segment>
     */
    public function aretes(): array
    {
        $n = \count($this->points);
        $aretes = [];
        for ($i = 0; $i < $n; ++$i) {
            $aretes[] = new Segment($this->points[$i], $this->points[($i + 1) % $n]);
        }

        return $aretes;
    }

    public function boiteEnglobante(): BoiteEnglobante
    {
        return BoiteEnglobante::depuisPoints($this->points);
    }

    /**
     * Polygone simple : au moins 3 sommets distincts, aire non nulle, aucune arête ne touche une arête non adjacente
     * et deux arêtes adjacentes ne se recouvrent pas.
     */
    public function estSimple(): bool
    {
        $n = \count($this->points);
        if ($n < 3 || 0 === $this->aireDoublee()) {
            return false;
        }
        for ($i = 0; $i < $n; ++$i) {
            if ($this->points[$i]->egal($this->points[($i + 1) % $n])) {
                return false;
            }
        }
        $aretes = $this->aretes();
        for ($i = 0; $i < $n; ++$i) {
            for ($j = $i + 1; $j < $n; ++$j) {
                if ($j === $i + 1 || ($i === 0 && $j === $n - 1)) {
                    // adjacentes : seul le sommet commun est autorisé
                    $commun = $j === $i + 1 ? $aretes[$j]->debut : $aretes[$i]->debut;
                    $a = $j === $i + 1 ? $aretes[$i]->debut : $aretes[$j]->debut;
                    $c = $j === $i + 1 ? $aretes[$j]->fin : $aretes[$i]->fin;
                    if (0 === self::orientation($a, $commun, $c) && self::produitScalaire($a->moins($commun), $c->moins($commun)) > 0) {
                        return false;
                    }
                    continue;
                }
                if (self::segmentsSeTouchent($aretes[$i], $aretes[$j])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Distance (mm, flottant de calcul) du point au bord.
     */
    public function distanceAuBord(Point2D $p): float
    {
        $min = INF;
        foreach ($this->aretes() as $arete) {
            $min = min($min, $arete->distanceAuPoint($p));
        }

        return $min;
    }

    /**
     * @param bool $bordInclus un point à moins d'1 mm du bord est considéré sur le bord
     */
    public function contient(Point2D $p, bool $bordInclus = true): bool
    {
        if ($this->distanceAuBord($p) <= Tolerance::MM) {
            return $bordInclus;
        }
        $dedans = false;
        foreach ($this->aretes() as $arete) {
            $a = $arete->debut;
            $b = $arete->fin;
            if (($a->y > $p->y) !== ($b->y > $p->y)) {
                $croix = ($b->x - $a->x) * ($p->y - $a->y) - ($b->y - $a->y) * ($p->x - $a->x);
                if ($b->y > $a->y ? $croix > 0 : $croix < 0) {
                    $dedans = !$dedans;
                }
            }
        }

        return $dedans;
    }

    /**
     * Le polygone contient entièrement l'autre (bord inclus) : sommets, milieux d'arêtes, aucun croisement franc.
     */
    public function contientPolygone(self $autre): bool
    {
        foreach ($autre->points as $p) {
            if (!$this->contient($p)) {
                return false;
            }
        }
        foreach ($autre->aretes() as $arete) {
            if (!$this->contient(self::milieu($arete))) {
                return false;
            }
        }

        return !$this->aCroisementFranc($autre);
    }

    /**
     * Les deux polygones ont au moins un point commun (contact des bords compris).
     */
    public function intersecte(self $autre): bool
    {
        foreach ($this->aretes() as $a) {
            foreach ($autre->aretes() as $b) {
                if (self::segmentsSeTouchent($a, $b)) {
                    return true;
                }
            }
        }

        return $this->contient($autre->points[0] ?? new Point2D(0, 0)) || $autre->contient($this->points[0] ?? new Point2D(0, 0));
    }

    /**
     * Les intérieurs se recouvrent (un simple contact de bords n'en est pas un ; pénétration de moins d'1 mm ignorée).
     */
    public function chevauche(self $autre): bool
    {
        if ($this->estRectangleAxes() && $autre->estRectangleAxes()) {
            $a = $this->boiteEnglobante();
            $b = $autre->boiteEnglobante();

            return min($a->maxX, $b->maxX) - max($a->minX, $b->minX) > Tolerance::MM
                && min($a->maxY, $b->maxY) - max($a->minY, $b->minY) > Tolerance::MM;
        }
        if ($this->aCroisementFranc($autre)) {
            return true;
        }
        foreach ([[$this, $autre], [$autre, $this]] as [$x, $y]) {
            foreach ($x->points as $p) {
                if ($y->contient($p, false)) {
                    return true;
                }
            }
            foreach ($x->aretes() as $arete) {
                if ($y->contient(self::milieu($arete), false)) {
                    return true;
                }
            }
            if ([] !== $x->points && $y->contient($x->moyenne(), false)) {
                return true;
            }
        }

        return false;
    }

    public function estRectangleAxes(): bool
    {
        if (4 !== \count($this->points)) {
            return false;
        }
        foreach ($this->aretes() as $arete) {
            if ($arete->debut->x !== $arete->fin->x && $arete->debut->y !== $arete->fin->y) {
                return false;
            }
        }

        return $this->aire() === $this->boiteEnglobante()->largeur() * $this->boiteEnglobante()->hauteur();
    }

    public function moyenne(): Point2D
    {
        $n = max(1, \count($this->points));

        return new Point2D(
            Tolerance::arrondir(array_sum(array_map(static fn (Point2D $p): int => $p->x, $this->points)) / $n),
            Tolerance::arrondir(array_sum(array_map(static fn (Point2D $p): int => $p->y, $this->points)) / $n),
        );
    }

    /**
     * Signe du produit vectoriel (b - a) x (c - a).
     */
    public static function orientation(Point2D $a, Point2D $b, Point2D $c): int
    {
        return ($b->x - $a->x) * ($c->y - $a->y) <=> ($b->y - $a->y) * ($c->x - $a->x);
    }

    public static function segmentsSeTouchent(Segment $s, Segment $t): bool
    {
        $o1 = self::orientation($s->debut, $s->fin, $t->debut);
        $o2 = self::orientation($s->debut, $s->fin, $t->fin);
        $o3 = self::orientation($t->debut, $t->fin, $s->debut);
        $o4 = self::orientation($t->debut, $t->fin, $s->fin);
        if ($o1 * $o2 < 0 && $o3 * $o4 < 0) {
            return true;
        }

        return (0 === $o1 && self::surSegment($s, $t->debut))
            || (0 === $o2 && self::surSegment($s, $t->fin))
            || (0 === $o3 && self::surSegment($t, $s->debut))
            || (0 === $o4 && self::surSegment($t, $s->fin));
    }

    private static function produitScalaire(Point2D $a, Point2D $b): int
    {
        return $a->x * $b->x + $a->y * $b->y;
    }

    private static function surSegment(Segment $s, Point2D $p): bool
    {
        return $p->x >= min($s->debut->x, $s->fin->x) && $p->x <= max($s->debut->x, $s->fin->x)
            && $p->y >= min($s->debut->y, $s->fin->y) && $p->y <= max($s->debut->y, $s->fin->y);
    }

    private static function milieu(Segment $s): Point2D
    {
        return new Point2D(Tolerance::arrondir(($s->debut->x + $s->fin->x) / 2), Tolerance::arrondir(($s->debut->y + $s->fin->y) / 2));
    }

    private function aCroisementFranc(self $autre): bool
    {
        foreach ($this->aretes() as $s) {
            foreach ($autre->aretes() as $t) {
                $o1 = self::orientation($s->debut, $s->fin, $t->debut);
                $o2 = self::orientation($s->debut, $s->fin, $t->fin);
                $o3 = self::orientation($t->debut, $t->fin, $s->debut);
                $o4 = self::orientation($t->debut, $t->fin, $s->fin);
                if ($o1 * $o2 < 0 && $o3 * $o4 < 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
