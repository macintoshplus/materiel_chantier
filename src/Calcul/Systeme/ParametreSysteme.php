<?php

declare(strict_types=1);

namespace App\Calcul\Systeme;

/**
 * Schéma d'un paramètre de système : nom, type (`int`, `string`, `bool`) et valeur par défaut.
 */
final readonly class ParametreSysteme
{
    public function __construct(public string $nom, public string $type, public int|string|bool|null $defaut = null)
    {
        if (!\in_array($type, ['int', 'string', 'bool'], true)) {
            throw new \InvalidArgumentException(\sprintf('Unknown parameter type "%s".', $type));
        }
    }

    public function accepte(mixed $valeur): bool
    {
        return match ($this->type) {
            'int' => \is_int($valeur),
            'string' => \is_string($valeur),
            default => \is_bool($valeur),
        };
    }
}
