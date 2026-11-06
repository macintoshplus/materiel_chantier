<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Impacts d'une modification, à afficher avant validation quand des données (ouvertures, ancrages) sont touchées.
 */
final readonly class PlanModification
{
    /**
     * @param list<Avertissement> $impacts
     */
    public function __construct(public array $impacts = [], public bool $confirmationRequise = false)
    {
    }

    public function estVide(): bool
    {
        return [] === $this->impacts && !$this->confirmationRequise;
    }
}
