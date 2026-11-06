<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paroi existante derrière un système (ce qu'on isole) : liste ordonnée de couches, du côté intérieur vers
 * l'extérieur. Elle ne contribue qu'au calcul thermique : ni prix, ni lot, ni quantité.
 */
#[ORM\Entity]
#[ORM\Table(name: 'paroi_existante')]
class ParoiExistante
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    /** @var Collection<int, CoucheExistante> */
    #[ORM\OneToMany(targetEntity: CoucheExistante::class, mappedBy: 'paroi', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    #[Assert\Valid]
    private Collection $couches;

    public function __construct(
        #[ORM\OneToOne(targetEntity: Surface::class, inversedBy: 'paroiExistante')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', unique: true)]
        private Surface $surface,
    ) {
        $this->couches = new ArrayCollection();
        $surface->definirParoiExistante($this);
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getSurface(): Surface
    {
        return $this->surface;
    }

    /**
     * @return Collection<int, CoucheExistante>
     */
    public function getCouches(): Collection
    {
        return $this->couches;
    }

    public function ajouterCouche(CoucheExistante $couche): void
    {
        if (!$this->couches->contains($couche)) {
            $this->couches->add($couche);
        }
    }

    /**
     * Résistance thermique de la paroi (millièmes de m².K/W).
     */
    public function resistanceMilli(): int
    {
        $total = 0;
        foreach ($this->couches as $couche) {
            $total += $couche->resistanceMilli();
        }

        return $total;
    }
}
