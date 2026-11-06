<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

/**
 * Liaison d'un élément à son parent dans l'arbre d'un réseau. `conducteurs` (actifs, hors terre) remplace la valeur
 * déduite des types lorsqu'il est renseigné.
 */
final readonly class Liaison
{
    public function __construct(public string $enfant, public string $parent, public ?int $conducteurs = null)
    {
    }
}
