<?php

declare(strict_types=1);

namespace App\Geometrie;

use App\Enum\TypeJonction;

/**
 * Jonction entre deux surfaces, exprimée par les identifiants des sommets de l'arête partagée (jamais par des
 * coordonnées). Les surfaces sont désignées par une clé opaque choisie par l'appelant.
 */
final readonly class JonctionDecrite
{
    public function __construct(
        public string $cleA,
        public string $debutA,
        public string $finA,
        public string $cleB,
        public string $debutB,
        public string $finB,
        public TypeJonction $type,
        public int $longueurA,
        public int $longueurB,
    ) {
    }
}
