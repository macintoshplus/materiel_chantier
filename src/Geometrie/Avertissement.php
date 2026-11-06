<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Message du domaine sans dépendance Symfony : un code stable et des paramètres.
 * L'adaptateur de l'application le convertit en message traduit.
 */
final readonly class Avertissement
{
    /**
     * @param array<string, scalar|null> $params
     */
    public function __construct(public string $code, public array $params = [])
    {
    }

    /**
     * @param array<string, scalar|null> $params
     */
    public function avec(array $params): self
    {
        return new self($this->code, [...$this->params, ...$params]);
    }
}
