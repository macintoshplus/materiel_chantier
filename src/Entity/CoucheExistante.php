<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Couche d'une paroi existante : soit un matériau de la catégorie `paroi_existante` avec son épaisseur (R déduit de
 * lambda), soit un R forfaitaire en millièmes de m².K/W. Pas de prix, pas de lot.
 */
#[ORM\Entity]
#[ORM\Table(name: 'couche_existante')]
class CoucheExistante
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Materiau::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Materiau $materiau = null;

    /** Épaisseur (mm), avec un matériau. */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $epaisseur = null;

    /** R forfaitaire (millièmes de m².K/W), sans matériau. */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $rForfaitaireMilli = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: ParoiExistante::class, inversedBy: 'couches')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ParoiExistante $paroi,
        #[ORM\Column]
        private int $ordre = 0,
    ) {
        $paroi->ajouterCouche($this);
    }

    #[Assert\IsTrue(message: 'An existing layer is either a material with a thickness or a fixed thermal resistance.')]
    public function isDefinitionValide(): bool
    {
        $avecMateriau = null !== $this->materiau && null !== $this->epaisseur && null === $this->rForfaitaireMilli;
        $forfait = null === $this->materiau && null === $this->epaisseur && null !== $this->rForfaitaireMilli;

        return $avecMateriau || $forfait;
    }

    #[Assert\IsTrue(message: 'The material of an existing layer must belong to the category "existing wall material".')]
    public function isCategorieValide(): bool
    {
        return null === $this->materiau || 'paroi_existante' === $this->materiau->getCategorie();
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getParoi(): ParoiExistante
    {
        return $this->paroi;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): void
    {
        $this->ordre = $ordre;
    }

    public function getMateriau(): ?Materiau
    {
        return $this->materiau;
    }

    public function getEpaisseur(): ?int
    {
        return $this->epaisseur;
    }

    public function getRForfaitaireMilli(): ?int
    {
        return $this->rForfaitaireMilli;
    }

    public function definirMateriau(Materiau $materiau, int $epaisseur): void
    {
        $this->materiau = $materiau;
        $this->epaisseur = $epaisseur;
        $this->rForfaitaireMilli = null;
    }

    public function definirForfait(int $rMilli): void
    {
        $this->materiau = null;
        $this->epaisseur = null;
        $this->rForfaitaireMilli = $rMilli;
    }

    /**
     * Résistance de la couche (millièmes de m².K/W) : le forfait, ou e / lambda avec lambda en cent-millièmes.
     */
    public function resistanceMilli(): int
    {
        if (null !== $this->rForfaitaireMilli) {
            return $this->rForfaitaireMilli;
        }
        $lambda = $this->materiau?->valeur('lambda');
        if (null === $this->epaisseur || (!\is_int($lambda) && !\is_float($lambda)) || $lambda <= 0) {
            return 0;
        }

        return (int) round($this->epaisseur * 100000 / (int) round($lambda * 100000));
    }
}
