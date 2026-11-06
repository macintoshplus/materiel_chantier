<?php

declare(strict_types=1);

namespace App\Geometrie;

final readonly class Point3D
{
    public function __construct(public int $x, public int $y, public int $z)
    {
    }

    /**
     * @param list<int> $liste
     */
    public static function depuisListe(array $liste): self
    {
        return new self($liste[0] ?? 0, $liste[1] ?? 0, $liste[2] ?? 0);
    }

    /**
     * @return list<int>
     */
    public function versListe(): array
    {
        return [$this->x, $this->y, $this->z];
    }

    public function distanceA(self $autre): int
    {
        return Tolerance::arrondir(sqrt(($this->x - $autre->x) ** 2 + ($this->y - $autre->y) ** 2 + ($this->z - $autre->z) ** 2));
    }

    public function norme(): float
    {
        return sqrt($this->x ** 2 + $this->y ** 2 + $this->z ** 2);
    }
}
