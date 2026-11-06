<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Axe d'une cloison : segment (début, fin) en mm. Immuable : chaque modification renvoie un nouveau tracé.
 */
final readonly class TraceCloison
{
    public function __construct(public Point2D $debut, public Point2D $fin)
    {
    }

    public function segment(): Segment
    {
        return new Segment($this->debut, $this->fin);
    }

    public function longueur(): int
    {
        return $this->segment()->longueur();
    }

    /**
     * Angle de l'axe par rapport à l'axe X, en degrés (valeur d'affichage, jamais persistée).
     */
    public function angleDegres(): float
    {
        return rad2deg(atan2($this->fin->y - $this->debut->y, $this->fin->x - $this->debut->x));
    }

    /**
     * Normale unitaire à gauche du sens de tracé (côté de la face A).
     *
     * @return array{float, float}
     */
    public function normaleGauche(): array
    {
        $v = $this->segment()->vecteur();
        $n = hypot($v->x, $v->y);
        if (0.0 === $n) {
            return [0.0, 0.0];
        }

        return [-$v->y / $n, $v->x / $n];
    }

    public function deplacerExtremite(bool $debut, Point2D $point): self
    {
        return $debut ? new self($point, $this->fin) : new self($this->debut, $point);
    }

    /**
     * Change la longueur en conservant le début et la direction.
     */
    public function definirLongueur(int $longueur): self
    {
        $actuelle = $this->longueur();
        if ($actuelle <= 0 || $longueur <= 0) {
            throw new ErreurGeometrique('partition_degenerate');
        }
        $v = $this->segment()->vecteur();

        return new self($this->debut, new Point2D(
            $this->debut->x + Tolerance::arrondir($v->x * $longueur / $actuelle),
            $this->debut->y + Tolerance::arrondir($v->y * $longueur / $actuelle),
        ));
    }

    /**
     * Fait pivoter le tracé autour de son début pour qu'il ait l'angle donné (degrés, repère du plan), longueur conservée.
     */
    public function definirAngleDegres(float $degres): self
    {
        $longueur = $this->longueur();
        if ($longueur <= 0) {
            throw new ErreurGeometrique('partition_degenerate');
        }
        $r = deg2rad($degres);

        return new self($this->debut, new Point2D(
            $this->debut->x + Tolerance::arrondir($longueur * cos($r)),
            $this->debut->y + Tolerance::arrondir($longueur * sin($r)),
        ));
    }

    public function translater(int $dx, int $dy): self
    {
        $d = new Point2D($dx, $dy);

        return new self($this->debut->plus($d), $this->fin->plus($d));
    }

    /**
     * Distance (mm) entre le milieu de l'axe et la droite de référence ; positive à gauche de la référence.
     */
    public function decalageParRapportA(Segment $reference): int
    {
        $milieu = new Point2D(intdiv($this->debut->x + $this->fin->x, 2), intdiv($this->debut->y + $this->fin->y, 2));

        return Tolerance::arrondir($reference->distanceSigneeALaDroite($milieu));
    }

    /**
     * Déplace le tracé parallèlement à lui-même pour que la distance à la droite de référence vaille `distance`,
     * du côté où il se trouve déjà (à gauche si indifférent).
     */
    public function decalerParRapportA(Segment $reference, int $distance): self
    {
        $v = $reference->vecteur();
        $n = hypot($v->x, $v->y);
        if (0.0 === $n) {
            throw new ErreurGeometrique('partition_degenerate');
        }
        $actuel = $this->decalageParRapportA($reference);
        $cible = $actuel < 0 ? -abs($distance) : abs($distance);
        $delta = $cible - $actuel;

        return $this->translater(Tolerance::arrondir(-$v->y / $n * $delta), Tolerance::arrondir($v->x / $n * $delta));
    }
}
