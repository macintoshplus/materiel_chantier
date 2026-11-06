<?php

declare(strict_types=1);

namespace App\Geometrie;

use App\Enum\CoteCloison;
use App\Enum\TypeJonction;

/**
 * Jonction d'une face de cloison. Côté A : arête de la face (identifiants). Côté B : surface cible (`cleB`, null pour
 * le sol) et, si elle partage des sommets, son arête ; `debutMm`/`finMm` : portion de l'arête cible couverte.
 */
final readonly class JonctionCloisonDecrite
{
    public function __construct(
        public TypeJonction $type,
        public CoteCloison $cote,
        public string $debutA,
        public string $finA,
        public ?string $cleB = null,
        public ?string $debutB = null,
        public ?string $finB = null,
        public ?int $debutMm = null,
        public ?int $finMm = null,
    ) {
    }
}
