<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Transformation d'une surface vers le repère de la pièce : origine 3D + vecteurs `u` et `v` entiers non normalisés
 * (seule leur direction compte ; la normalisation se fait à la lecture, rien de flottant n'est persisté).
 * Hypothèse : `u` et `v` sont orthogonaux ; `u x v` pointe vers l'intérieur de la pièce (côté d'où l'on regarde).
 */
final readonly class Plan
{
    public function __construct(public Point3D $origine, public Point3D $u, public Point3D $v)
    {
    }

    /**
     * @param list<int> $origine
     * @param list<int> $u
     * @param list<int> $v
     */
    public static function depuisListes(array $origine, array $u, array $v): self
    {
        return new self(Point3D::depuisListe($origine), Point3D::depuisListe($u), Point3D::depuisListe($v));
    }

    public function versPiece(Point2D $p): Point3D
    {
        $nu = $this->u->norme();
        $nv = $this->v->norme();
        $a = 0.0 === $nu ? 0.0 : $p->x / $nu;
        $b = 0.0 === $nv ? 0.0 : $p->y / $nv;

        return new Point3D(
            Tolerance::arrondir($this->origine->x + $a * $this->u->x + $b * $this->v->x),
            Tolerance::arrondir($this->origine->y + $a * $this->u->y + $b * $this->v->y),
            Tolerance::arrondir($this->origine->z + $a * $this->u->z + $b * $this->v->z),
        );
    }

    /**
     * Longueur réelle (mm) d'un segment de la surface, mesurée en 3D.
     */
    public function longueurReelle(Segment $segment): int
    {
        return $this->versPiece($segment->debut)->distanceA($this->versPiece($segment->fin));
    }

    /**
     * @return array{float, float, float} normale unitaire (vers l'intérieur de la pièce)
     */
    public function normale(): array
    {
        $x = $this->u->y * $this->v->z - $this->u->z * $this->v->y;
        $y = $this->u->z * $this->v->x - $this->u->x * $this->v->z;
        $z = $this->u->x * $this->v->y - $this->u->y * $this->v->x;
        $n = sqrt($x * $x + $y * $y + $z * $z);
        if (0.0 === $n) {
            return [0.0, 0.0, 0.0];
        }

        return [$x / $n, $y / $n, $z / $n];
    }

    /**
     * Angle entre la surface et l'horizontale, en degrés (0 = plat, 90 = vertical). Valeur de calcul, jamais persistée.
     */
    public function anglePenteDegres(): float
    {
        return rad2deg(acos(min(1.0, abs($this->normale()[2]))));
    }

    public function estHorizontal(): bool
    {
        return abs($this->normale()[2]) > 0.999999;
    }

    public function estVertical(): bool
    {
        return abs($this->normale()[2]) < 0.000001;
    }

    /**
     * Cote (mm) du plan à la verticale du point (x, y) de la pièce ; null pour une surface verticale.
     */
    public function coteAu(Point2D $p): ?int
    {
        [$nx, $ny, $nz] = $this->normale();
        if (abs($nz) < 0.000001) {
            return null;
        }

        return Tolerance::arrondir($this->origine->z - ($nx * ($p->x - $this->origine->x) + $ny * ($p->y - $this->origine->y)) / $nz);
    }
}
