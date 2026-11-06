<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

final readonly class MargeRetenue
{
    public function __construct(public int $centiemes, public SourceMarge $source)
    {
    }
}
