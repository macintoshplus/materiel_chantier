<?php

declare(strict_types=1);

namespace App\Geometrie;

final readonly class BoiteEnglobante
{
    public function __construct(public int $minX, public int $minY, public int $maxX, public int $maxY)
    {
    }

    /**
     * @param list<Point2D> $points
     */
    public static function depuisPoints(array $points): self
    {
        $minX = $minY = PHP_INT_MAX;
        $maxX = $maxY = PHP_INT_MIN;
        foreach ($points as $p) {
            $minX = min($minX, $p->x);
            $minY = min($minY, $p->y);
            $maxX = max($maxX, $p->x);
            $maxY = max($maxY, $p->y);
        }

        return new self($minX, $minY, $maxX, $maxY);
    }

    public function largeur(): int
    {
        return $this->maxX - $this->minX;
    }

    public function hauteur(): int
    {
        return $this->maxY - $this->minY;
    }
}
