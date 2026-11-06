<?php

declare(strict_types=1);

namespace App\Geometrie;

final readonly class FacesCloison
{
    public function __construct(public SurfaceGeneree $a, public SurfaceGeneree $b, public int $epaisseur)
    {
    }
}
