<?php

declare(strict_types=1);

namespace App\Geometrie;

final readonly class Point2D
{
    public function __construct(public int $x, public int $y)
    {
    }

    /**
     * @param array{x: int, y: int} $p
     */
    public static function depuisTableau(array $p): self
    {
        return new self($p['x'], $p['y']);
    }

    /**
     * @return array{x: int, y: int}
     */
    public function versTableau(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }

    public function plus(self $autre): self
    {
        return new self($this->x + $autre->x, $this->y + $autre->y);
    }

    public function moins(self $autre): self
    {
        return new self($this->x - $autre->x, $this->y - $autre->y);
    }

    public function egal(self $autre): bool
    {
        return $this->x === $autre->x && $this->y === $autre->y;
    }

    public function distanceA(self $autre): int
    {
        return Tolerance::arrondir(hypot($this->x - $autre->x, $this->y - $autre->y));
    }
}
