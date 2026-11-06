<?php

declare(strict_types=1);

namespace App\Form\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie d'un système appliqué : nom de la variante, paramètres du schéma de la définition (`null` = valeur par
 * défaut du moteur) et couches regroupées par emplacement.
 */
final class SystemeSaisie
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $variante = '';

    /** @var array<string, scalar|null> */
    public array $parametres = [];

    /** @var array<string, list<CoucheSaisie>> */
    #[Assert\Valid]
    public array $couches = [];
}
