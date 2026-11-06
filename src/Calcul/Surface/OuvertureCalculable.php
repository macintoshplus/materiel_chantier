<?php

declare(strict_types=1);

namespace App\Calcul\Surface;

use App\Enum\TypePose;
use App\Geometrie\Polygone;

/**
 * Ouverture dans le repère `(u, v)` de la surface (mm), avec ce que les calculateurs lisent d'elle.
 */
final readonly class OuvertureCalculable
{
    public function __construct(
        public Polygone $forme,
        public string $type,
        public TypePose $typePose = TypePose::Tunnel,
        public ?string $typeContour = null,
        public int $refoncement = 0,
    ) {
    }
}
