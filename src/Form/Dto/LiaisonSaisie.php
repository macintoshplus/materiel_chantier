<?php

declare(strict_types=1);

namespace App\Form\Dto;

use App\Entity\Equipement;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Liaison d'un équipement à son parent dans l'arbre d'un circuit. `conducteurs` (actifs, hors terre) est vide pour la
 * valeur déduite des types ; 3 pour une liaison de va-et-vient (3 conducteurs actifs + terre).
 */
final class LiaisonSaisie
{
    #[Assert\NotNull]
    public ?Equipement $equipement = null;

    #[Assert\NotNull]
    public ?Equipement $parent = null;

    #[Assert\Range(min: 1, max: 6)]
    public ?int $conducteurs = null;
}
