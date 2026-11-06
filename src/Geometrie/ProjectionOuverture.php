<?php

declare(strict_types=1);

namespace App\Geometrie;

final readonly class ProjectionOuverture
{
    public function __construct(public Polygone $faceA, public Polygone $faceB)
    {
    }
}
