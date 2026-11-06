<?php

declare(strict_types=1);

namespace App\Form\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie d'une couche d'un système : l'article épinglé (identifiant, `null` = non épinglé, résolu par la
 * spécification), puis orientation, position, face (cloison), décalage et épaisseur imposée quand l'emplacement les permet.
 */
final class CoucheSaisie
{
    public ?int $materiau = null;

    public ?int $orientation = null;

    public ?string $position = null;

    public ?string $face = null;

    public ?int $decalage = null;

    #[Assert\Positive]
    public ?int $epaisseurOverride = null;
}
