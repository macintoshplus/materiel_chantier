<?php

declare(strict_types=1);

namespace App\Chiffrage;

use App\Entity\Materiau;

final readonly class Besoin
{
    public function __construct(public Materiau $materiau, public QuantiteBrute $quantite)
    {
    }
}
