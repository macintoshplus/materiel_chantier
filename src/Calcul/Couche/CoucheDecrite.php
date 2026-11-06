<?php

declare(strict_types=1);

namespace App\Calcul\Couche;

use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;

/**
 * Description pure d'une couche posée (miroir de `CoucheAppliquee`) : ce que le pipeline reçoit.
 * Le matériau est celui épinglé ; sinon il est résolu par la spécification.
 */
final readonly class CoucheDecrite
{
    public function __construct(
        public int $ordre,
        public string $emplacement,
        public string $role,
        public ?MaterielSpecification $specification = null,
        public ?MaterielLisible $materiau = null,
        /** Degrés, relative à la direction de l'ossature de la surface (0 = parallèle, 90 = perpendiculaire). */
        public int $orientation = 0,
        public ?PositionCouche $position = null,
        /** Décalage du calepinage (mm, le long de `u`) par rapport à la peau précédente. */
        public ?int $decalage = null,
        public ?int $epaisseurOverride = null,
        public ?string $face = null,
    ) {
    }
}
