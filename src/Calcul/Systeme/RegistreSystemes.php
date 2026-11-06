<?php

declare(strict_types=1);

namespace App\Calcul\Systeme;

final class RegistreSystemes
{
    /** @var array<string, DefinitionSysteme> */
    private array $definitions = [];

    /**
     * @param iterable<DefinitionSysteme> $definitions
     */
    public function __construct(iterable $definitions)
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->code()] = $definition;
        }
        ksort($this->definitions);
    }

    public function possede(string $code): bool
    {
        return isset($this->definitions[$code]);
    }

    public function get(string $code): DefinitionSysteme
    {
        return $this->definitions[$code] ?? throw new \InvalidArgumentException(\sprintf('Unknown system definition "%s".', $code));
    }

    /**
     * @return array<string, DefinitionSysteme>
     */
    public function toutes(): array
    {
        return $this->definitions;
    }
}
