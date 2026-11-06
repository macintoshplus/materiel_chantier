<?php

declare(strict_types=1);

namespace App\Calcul\Pipeline;

use App\Calcul\Couche\LigneBesoin;

/**
 * Besoin d'un système avec la face qui le porte : `null` pour ce qui est commun (ossature, isolant d'une cloison
 * ou tout ce qui est posé sur une surface), `A` ou `B` pour les parements d'une cloison.
 */
final readonly class LigneSysteme
{
    public function __construct(public ?string $face, public LigneBesoin $ligne)
    {
    }
}
