<?php

declare(strict_types=1);

namespace App\Geometrie;

final readonly class Sommet
{
    public function __construct(public string $id, public Point2D $point)
    {
    }
}
