<?php

declare(strict_types=1);

namespace App\Catalogue\Vue;

final readonly class PlageFixable
{
    public function __construct(public int $min, public int $max)
    {
    }

    public function contient(int $epaisseur): bool
    {
        return $epaisseur >= $this->min && $epaisseur <= $this->max;
    }
}
