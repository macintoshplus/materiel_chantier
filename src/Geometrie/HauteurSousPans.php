<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Plafond composé de pans (plan quelconque) : hauteur lue sur le plan du pan situé au-dessus du point.
 */
final readonly class HauteurSousPans implements HauteurPlafond
{
    /** Écart toléré (mm) pour rattacher un point situé juste hors de l'emprise d'un pan (arrondis). */
    private const PROCHE = 5;

    /**
     * @param list<PanPlafond> $pans
     */
    public function __construct(private array $pans)
    {
    }

    public function hauteurA(Point2D $point): ?int
    {
        $pan = $this->pan($point);

        return null === $pan ? null : $pan->plan->coteAu($point);
    }

    public function cleA(Point2D $point): ?string
    {
        return $this->pan($point)?->cle;
    }

    public function ruptures(Segment $segment): array
    {
        $candidats = [];
        foreach ($this->pans as $pan) {
            foreach ($pan->projection()->aretes() as $arete) {
                $p = self::croisement($segment, $arete);
                if (null !== $p) {
                    $candidats[] = [$segment->positionDe($p), $p];
                }
            }
        }
        usort($candidats, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $longueur = $segment->longueur();
        $points = [[0, $segment->debut]];
        foreach ($candidats as [$position, $p]) {
            [$derniere] = $points[\count($points) - 1];
            if ($position - $derniere > Tolerance::MM && $longueur - $position > Tolerance::MM) {
                $points[] = [$position, $p];
            }
        }
        $points[] = [$longueur, $segment->fin];

        $ruptures = [];
        for ($i = 1; $i < \count($points) - 1; ++$i) {
            [$pa, $a] = $points[$i - 1];
            [$pb, $b] = $points[$i];
            [$pc, $c] = $points[$i + 1];
            $ha = $this->hauteurA($a);
            $hb = $this->hauteurA($b);
            $hc = $this->hauteurA($c);
            if (null !== $ha && null !== $hb && null !== $hc && $pc > $pa) {
                $attendue = $ha + ($hc - $ha) * ($pb - $pa) / ($pc - $pa);
                if (abs($attendue - $hb) <= Tolerance::MM) {
                    continue;
                }
            }
            $ruptures[] = $b;
        }

        return $ruptures;
    }

    private function pan(Point2D $point): ?PanPlafond
    {
        $meilleur = null;
        $distance = INF;
        foreach ($this->pans as $pan) {
            $projection = $pan->projection();
            if ($projection->contient($point)) {
                return $pan;
            }
            $d = $projection->distanceAuBord($point);
            if ($d <= self::PROCHE && $d < $distance) {
                $meilleur = $pan;
                $distance = $d;
            }
        }

        return $meilleur;
    }

    /**
     * Point de croisement franc (strictement à l'intérieur de `$s`) avec le segment `$t`, null sinon.
     */
    private static function croisement(Segment $s, Segment $t): ?Point2D
    {
        $r = $s->vecteur();
        $q = $t->vecteur();
        $d = $r->x * $q->y - $r->y * $q->x;
        if (0 === $d) {
            return null;
        }
        $w = $t->debut->moins($s->debut);
        $a = ($w->x * $q->y - $w->y * $q->x) / $d;
        $b = ($w->x * $r->y - $w->y * $r->x) / $d;
        if ($a <= 0.0 || $a >= 1.0 || $b < 0.0 || $b > 1.0) {
            return null;
        }

        return new Point2D(Tolerance::arrondir($s->debut->x + $a * $r->x), Tolerance::arrondir($s->debut->y + $a * $r->y));
    }
}
