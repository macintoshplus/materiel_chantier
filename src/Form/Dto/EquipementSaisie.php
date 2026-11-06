<?php

declare(strict_types=1);

namespace App\Form\Dto;

use App\Entity\Equipement;
use App\Entity\Reseau;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie d'un équipement posé sur une surface : type du registre de sa famille, position `(u, v)` en mm, réseau.
 */
final class EquipementSaisie
{
    #[Assert\NotBlank]
    public ?string $type = null;

    #[Assert\Length(max: 255)]
    public ?string $nom = null;

    #[Assert\NotNull]
    public ?int $u = 0;

    #[Assert\NotNull]
    public ?int $v = 0;

    public ?Reseau $reseau = null;

    #[Assert\PositiveOrZero]
    public ?int $puissanceW = null;

    public static function depuis(Equipement $equipement): self
    {
        $saisie = new self();
        $saisie->type = $equipement->getType();
        $saisie->nom = $equipement->getNom();
        $saisie->u = $equipement->getU();
        $saisie->v = $equipement->getV();
        $saisie->reseau = $equipement->getReseau();
        $saisie->puissanceW = $equipement->getPuissanceW();

        return $saisie;
    }
}
