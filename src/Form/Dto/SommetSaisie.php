<?php

declare(strict_types=1);

namespace App\Form\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un sommet du sol (mm). `supprimer` demande la fusion des deux murs voisins.
 */
final class SommetSaisie
{
    public string $id = '';

    #[Assert\NotNull]
    public ?int $x = null;

    #[Assert\NotNull]
    public ?int $y = null;

    public bool $supprimer = false;
}
