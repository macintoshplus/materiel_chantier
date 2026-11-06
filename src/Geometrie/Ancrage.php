<?php

declare(strict_types=1);

namespace App\Geometrie;

use App\Enum\TypeAncrage;

/**
 * Ancrage proposé d'une extrémité de cloison. `position` : abscisse (mm) du point d'appui le long de l'arête basse de la cible.
 */
final readonly class Ancrage
{
    public function __construct(
        public TypeAncrage $type,
        public ?CibleAncrage $cible = null,
        public ?int $position = null,
    ) {
    }

    public static function libre(): self
    {
        return new self(TypeAncrage::Libre);
    }

    /**
     * @return array{type: string, cible: int|null, position: int|null}
     */
    public function versTableau(): array
    {
        return ['type' => $this->type->value, 'cible' => $this->cible?->id, 'position' => $this->position];
    }
}
