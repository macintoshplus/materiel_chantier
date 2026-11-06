<?php

declare(strict_types=1);

namespace App\Entity;

use App\Catalogue\MaterielLisible;
use App\Constraints\CaracteristiquesValides;
use App\Repository\MateriauRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MateriauRepository::class)]
#[ORM\Table(name: 'materiau')]
#[ORM\UniqueConstraint(name: 'uniq_materiau_reference', columns: ['reference'])]
#[ORM\Index(name: 'idx_materiau_categorie', columns: ['categorie'])]
#[UniqueEntity(fields: ['reference'], message: 'This reference is already used.')]
#[CaracteristiquesValides]
final class Materiau implements MaterielLisible
{
    private const array COLONNES = ['longueur', 'largeur', 'epaisseur', 'hauteur', 'poids', 'unites_par_lot', 'unite_vente'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $reference = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $libelle = '';

    #[ORM\Column(length: 48)]
    #[Assert\NotBlank]
    private string $categorie = '';

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $longueur = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $largeur = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $epaisseur = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $hauteur = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    private string $uniteVente = 'piece';

    #[ORM\Column(options: ['default' => 1])]
    #[Assert\GreaterThanOrEqual(1)]
    private int $unitesParLot = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $poids = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $margeSecuritePct = null;

    /** @var array<array-key, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $caracteristiques = [];

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    public function __clone()
    {
        unset($this->id);
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): void
    {
        $this->libelle = $libelle;
    }

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): void
    {
        $this->categorie = $categorie;
    }

    public function getLongueur(): ?int
    {
        return $this->longueur;
    }

    public function setLongueur(?int $longueur): void
    {
        $this->longueur = $longueur;
    }

    public function getLargeur(): ?int
    {
        return $this->largeur;
    }

    public function setLargeur(?int $largeur): void
    {
        $this->largeur = $largeur;
    }

    public function getEpaisseur(): ?int
    {
        return $this->epaisseur;
    }

    public function setEpaisseur(?int $epaisseur): void
    {
        $this->epaisseur = $epaisseur;
    }

    public function getHauteur(): ?int
    {
        return $this->hauteur;
    }

    public function setHauteur(?int $hauteur): void
    {
        $this->hauteur = $hauteur;
    }

    public function getUniteVente(): string
    {
        return $this->uniteVente;
    }

    public function setUniteVente(string $uniteVente): void
    {
        $this->uniteVente = $uniteVente;
    }

    public function getUnitesParLot(): int
    {
        return $this->unitesParLot;
    }

    public function setUnitesParLot(int $unitesParLot): void
    {
        $this->unitesParLot = $unitesParLot;
    }

    public function getPoids(): ?string
    {
        return $this->poids;
    }

    public function setPoids(?string $poids): void
    {
        $this->poids = $poids;
    }

    public function getMargeSecuritePct(): ?string
    {
        return $this->margeSecuritePct;
    }

    public function setMargeSecuritePct(?string $margeSecuritePct): void
    {
        $this->margeSecuritePct = $margeSecuritePct;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getCaracteristiques(): array
    {
        return $this->caracteristiques;
    }

    /**
     * @param array<array-key, mixed> $caracteristiques
     */
    public function setCaracteristiques(array $caracteristiques): void
    {
        $this->caracteristiques = $caracteristiques;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): void
    {
        $this->actif = $actif;
    }

    public function categorie(): string
    {
        return $this->categorie;
    }

    public function valeur(string $chemin): mixed
    {
        if (\in_array($chemin, ['reference', 'libelle', 'marge_securite_pct'], true)) {
            return match ($chemin) {
                'reference' => $this->reference,
                'libelle' => $this->libelle,
                default => $this->margeSecuritePct,
            };
        }
        if (\in_array($chemin, self::COLONNES, true)) {
            return match ($chemin) {
                'longueur' => $this->longueur,
                'largeur' => $this->largeur,
                'epaisseur' => $this->epaisseur,
                'hauteur' => $this->hauteur,
                'poids' => $this->poids,
                'unites_par_lot' => $this->unitesParLot,
                default => $this->uniteVente,
            };
        }

        $courant = $this->caracteristiques;
        foreach (explode('.', $chemin) as $segment) {
            if (!\is_array($courant) || !\array_key_exists($segment, $courant)) {
                return null;
            }
            $courant = $courant[$segment];
        }

        return $courant;
    }
}
