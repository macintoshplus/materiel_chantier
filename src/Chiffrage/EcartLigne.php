<?php

declare(strict_types=1);

namespace App\Chiffrage;

/**
 * Écart d'une ligne entre deux chiffrages (centimes) ; `null` quand la ligne n'existe pas d'un côté.
 */
final readonly class EcartLigne
{
    public function __construct(
        public string $cle,
        public string $libelle,
        public ?int $totalA,
        public ?int $totalB,
    ) {
    }

    public function ecart(): int
    {
        return ($this->totalB ?? 0) - ($this->totalA ?? 0);
    }
}
