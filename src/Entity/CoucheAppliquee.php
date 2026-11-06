<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CoteCloison;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une couche posée : un emplacement de la définition, une spécification (ce qu'on cherche) et, si l'utilisateur
 * l'a choisi, un article épinglé. Rôle et emplacement sont des chaînes libres : la définition du système fait foi.
 */
#[ORM\Entity]
#[ORM\Table(name: 'couche_appliquee')]
final class CoucheAppliquee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 1, nullable: true, enumType: CoteCloison::class)]
    private ?CoteCloison $face = null;

    /** @var array<array-key, mixed>|null JSON de `SpecificationSerialiseur` */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $specification = null;

    #[ORM\ManyToOne(targetEntity: Materiau::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Materiau $materiau = null;

    /** Degrés relatifs à la direction de l'ossature de la surface. */
    #[ORM\Column(nullable: true)]
    private ?int $orientation = null;

    /** Valeur de `PositionCouche`, nulle pour une attache ou une ossature. */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $position = null;

    /** @var array<string, int>|null décalage du calepinage (mm) */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $decalage = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $epaisseurOverride = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: SystemeApplique::class, inversedBy: 'couches')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private SystemeApplique $systemeApplique,
        #[ORM\Column]
        private int $ordre,
        #[ORM\Column(length: 64)]
        #[Assert\NotBlank]
        private string $role,
        #[ORM\Column(length: 64)]
        #[Assert\NotBlank]
        private string $emplacement,
    ) {
        $systemeApplique->ajouterCouche($this);
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getSystemeApplique(): SystemeApplique
    {
        return $this->systemeApplique;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): void
    {
        $this->ordre = $ordre;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getEmplacement(): string
    {
        return $this->emplacement;
    }

    public function getFace(): ?CoteCloison
    {
        return $this->face;
    }

    public function setFace(?CoteCloison $face): void
    {
        $this->face = $face;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getSpecification(): ?array
    {
        return $this->specification;
    }

    /**
     * @param array<array-key, mixed>|null $specification
     */
    public function setSpecification(?array $specification): void
    {
        $this->specification = $specification;
    }

    public function getMateriau(): ?Materiau
    {
        return $this->materiau;
    }

    public function setMateriau(?Materiau $materiau): void
    {
        $this->materiau = $materiau;
    }

    public function getOrientation(): ?int
    {
        return $this->orientation;
    }

    public function setOrientation(?int $orientation): void
    {
        $this->orientation = $orientation;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): void
    {
        $this->position = $position;
    }

    /**
     * @return array<string, int>|null
     */
    public function getDecalage(): ?array
    {
        return $this->decalage;
    }

    /**
     * @param array<string, int>|null $decalage
     */
    public function setDecalage(?array $decalage): void
    {
        $this->decalage = $decalage;
    }

    public function getEpaisseurOverride(): ?int
    {
        return $this->epaisseurOverride;
    }

    public function setEpaisseurOverride(?int $epaisseurOverride): void
    {
        $this->epaisseurOverride = $epaisseurOverride;
    }
}
