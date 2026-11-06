<?php

declare(strict_types=1);

namespace App\Geometrie;

final readonly class OuvertureAPlacer
{
    public function __construct(public string $nom, public Polygone $forme)
    {
    }
}
