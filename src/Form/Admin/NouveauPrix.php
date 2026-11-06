<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Fournisseur;
use Symfony\Component\Validator\Constraints as Assert;

final class NouveauPrix
{
    #[Assert\NotNull]
    public ?Fournisseur $fournisseur = null;

    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    public ?string $prixLotTtc = null;

    #[Assert\Positive]
    public ?int $unitesParLot = null;

    public ?string $referenceFournisseur = null;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $dateDebut = null;
}
