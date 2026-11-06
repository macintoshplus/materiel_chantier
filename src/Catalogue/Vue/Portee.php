<?php

declare(strict_types=1);

namespace App\Catalogue\Vue;

/**
 * Ligne de la table de portées du fabricant, identifiée par (renfort, orientation, entraxe).
 */
final readonly class Portee
{
    /**
     * @param list<AccessoireLie> $accessoires
     */
    public function __construct(
        public Renfort $renfort,
        public Orientation $orientation,
        public int $entraxe,
        public int $longueurMax,
        public ?int $decalageJointsMinMm = null,
        public ?int $pasVisAssemblageMm = null,
        public array $accessoires = [],
    ) {
    }
}
