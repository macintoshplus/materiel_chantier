<?php

declare(strict_types=1);

namespace App\Form\Dto;

use App\Entity\Ouverture;
use App\Enum\TypePose;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie d'une ouverture (mm) : l'identifiant, absent pour une nouvelle ouverture, la rattache à l'entité existante.
 */
final class OuvertureSaisie
{
    public ?string $id = null;

    public ?string $nom = null;

    #[Assert\Choice(choices: ['fenetre', 'porte'])]
    public string $type = 'fenetre';

    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $largeur = 900;

    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $hauteur = 1200;

    #[Assert\NotNull]
    public ?int $u = 0;

    #[Assert\NotNull]
    public ?int $v = 900;

    public TypePose $typePose = TypePose::Applique;

    #[Assert\PositiveOrZero]
    public ?int $refoncement = 0;

    public static function depuis(Ouverture $ouverture): self
    {
        $saisie = new self();
        $saisie->id = null === $ouverture->getId() ? null : (string) $ouverture->getId();
        $saisie->nom = $ouverture->getNom();
        $saisie->type = $ouverture->getType();
        $saisie->largeur = $ouverture->getLargeur();
        $saisie->hauteur = $ouverture->getHauteur();
        $saisie->u = $ouverture->getU();
        $saisie->v = $ouverture->getV();
        $saisie->typePose = $ouverture->getTypePose();
        $saisie->refoncement = $ouverture->getRefoncement();

        return $saisie;
    }
}
