<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Système posé sur une surface (ou sur une cloison, pour les deux faces). Le code de la définition est une simple
 * chaîne : ajouter un système ne modifie ni cette entité ni le moteur. Plusieurs variantes sont possibles par
 * support, une seule est active.
 */
#[ORM\Entity]
#[ORM\Table(name: 'systeme_applique')]
final class SystemeApplique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Surface::class, inversedBy: 'systemes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Surface $surface = null;

    #[ORM\ManyToOne(targetEntity: Cloison::class, inversedBy: 'systemes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Cloison $cloison = null;

    #[ORM\Column(length: 255)]
    private string $variante = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $actif = false;

    /** @var array<string, scalar|null> */
    #[ORM\Column(type: Types::JSON)]
    private array $parametres = [];

    /** @var Collection<int, CoucheAppliquee> */
    #[ORM\OneToMany(targetEntity: CoucheAppliquee::class, mappedBy: 'systemeApplique', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $couches;

    public function __construct(
        #[ORM\Column(length: 64)]
        #[Assert\NotBlank]
        private string $definition,
        ?Surface $surface = null,
        ?Cloison $cloison = null,
    ) {
        $this->couches = new ArrayCollection();
        $this->surface = $surface;
        $this->cloison = $cloison;
        $surface?->ajouterSysteme($this);
        $cloison?->ajouterSysteme($this);
    }

    #[Assert\IsTrue(message: 'A system belongs to exactly one surface or one partition.')]
    public function isSupportValide(): bool
    {
        return (null === $this->surface) !== (null === $this->cloison);
    }

    #[Assert\IsTrue(message: 'Only one system can be active per support.')]
    public function isUnSeulActifValide(): bool
    {
        if (!$this->actif) {
            return true;
        }
        $freres = $this->surface?->getSystemes() ?? $this->cloison?->getSystemes() ?? new ArrayCollection();
        foreach ($freres as $autre) {
            if ($autre !== $this && $autre->isActif()) {
                return false;
            }
        }

        return true;
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getSurface(): ?Surface
    {
        return $this->surface;
    }

    public function getCloison(): ?Cloison
    {
        return $this->cloison;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function getVariante(): string
    {
        return $this->variante;
    }

    public function setVariante(string $variante): void
    {
        $this->variante = $variante;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    /**
     * Active ce système et désactive les autres variantes du même support.
     */
    public function activer(): void
    {
        $freres = $this->surface?->getSystemes() ?? $this->cloison?->getSystemes() ?? new ArrayCollection();
        foreach ($freres as $autre) {
            if ($autre !== $this) {
                $autre->actif = false;
            }
        }
        $this->actif = true;
    }

    public function desactiver(): void
    {
        $this->actif = false;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getParametres(): array
    {
        return $this->parametres;
    }

    /**
     * @param array<string, scalar|null> $parametres
     */
    public function setParametres(array $parametres): void
    {
        $this->parametres = $parametres;
    }

    /**
     * @return Collection<int, CoucheAppliquee>
     */
    public function getCouches(): Collection
    {
        return $this->couches;
    }

    public function ajouterCouche(CoucheAppliquee $couche): void
    {
        if (!$this->couches->contains($couche)) {
            $this->couches->add($couche);
        }
    }
}
