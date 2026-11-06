<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

use App\Catalogue\Vue\Portee;
use App\Catalogue\Vue\Renfort;

/**
 * Configuration d'une ossature : série du profilé, entraxe, renfort et zone d'application. `portee` est la ligne
 * de la table du fabricant qui la justifie ; null pour une configuration proposée par l'utilisateur hors table.
 */
final readonly class ConfigurationOssature
{
    /**
     * @param list<int> $positions positions (indices de montants) quand la zone est `positions_listees`
     */
    public function __construct(
        public int $serie,
        public int $entraxe,
        public Renfort $renfort,
        public ZoneRenfort $zone = ZoneRenfort::ToutesPositions,
        public array $positions = [],
        public ?Portee $portee = null,
    ) {
    }

    public function estHorsTable(): bool
    {
        return null === $this->portee;
    }

    /**
     * @param list<int> $positions
     */
    public function avecZone(ZoneRenfort $zone, array $positions = []): self
    {
        return new self($this->serie, $this->entraxe, $this->renfort, $zone, $positions, $this->portee);
    }
}
