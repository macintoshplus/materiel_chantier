<?php

declare(strict_types=1);

namespace App\Catalogue\Vue;

final class CaracteristiqueManquante extends \RuntimeException
{
    public static function pour(string $chemin): self
    {
        return new self(\sprintf('Required characteristic "%s" is missing.', $chemin));
    }
}
