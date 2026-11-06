<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypeLigneChiffrage;
use App\Enum\TypeStrategie;
use App\Repository\ChiffrageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Instantané immuable des besoins d'un projet, valorisés à une date : aucune modification après création, aucune
 * dépendance au catalogue (les lignes copient libellés, dimensions et prix). Montants en centimes d'euro.
 */
#[ORM\Entity(repositoryClass: ChiffrageRepository::class)]
#[ORM\Table(name: 'chiffrage')]
final class Chiffrage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    /** Somme des lignes `article` et `livraison` (les lignes `repartition` ne s'additionnent pas). */
    #[ORM\Column(options: ['default' => 0])]
    private int $totalTtcCentimes = 0;

    /** @var Collection<int, LigneChiffrage> */
    #[ORM\OneToMany(targetEntity: LigneChiffrage::class, mappedBy: 'chiffrage', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $lignes;

    /**
     * @param array<string, mixed>                              $parametres      marges, fournisseur imposé, adresse de référence, choix manuels
     * @param list<array{code: string, params: array<string, scalar>}> $avertissements avertissements de calcul et de substitution
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Projet::class, inversedBy: 'chiffrages')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Projet $projet,
        #[ORM\Column(length: 255)]
        private string $libelle,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $dateCreation,
        #[ORM\Column(length: 32, enumType: TypeStrategie::class)]
        private TypeStrategie $strategieFournisseur,
        #[ORM\Column(type: Types::JSON)]
        private array $parametres = [],
        #[ORM\Column(type: Types::JSON)]
        private array $avertissements = [],
    ) {
        $this->lignes = new ArrayCollection();
        $projet->getChiffrages()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getProjet(): Projet
    {
        return $this->projet;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function getStrategieFournisseur(): TypeStrategie
    {
        return $this->strategieFournisseur;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParametres(): array
    {
        return $this->parametres;
    }

    /**
     * @return list<array{code: string, params: array<string, scalar>}>
     */
    public function getAvertissements(): array
    {
        return $this->avertissements;
    }

    /**
     * Uniquement à la création du chiffrage.
     *
     * @param list<array{code: string, params: array<string, scalar>}> $avertissements
     */
    public function definirAvertissements(array $avertissements): void
    {
        $this->avertissements = $avertissements;
    }

    public function getTotalTtcCentimes(): int
    {
        return $this->totalTtcCentimes;
    }

    /**
     * @return Collection<int, LigneChiffrage>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    /**
     * @return list<LigneChiffrage>
     */
    public function lignesDeType(TypeLigneChiffrage $type): array
    {
        return array_values($this->lignes->filter(static fn (LigneChiffrage $l): bool => $l->getType() === $type)->toArray());
    }

    /**
     * Uniquement à la création du chiffrage.
     */
    public function ajouterLigne(LigneChiffrage $ligne): void
    {
        $this->lignes->add($ligne);
        if (TypeLigneChiffrage::Repartition !== $ligne->getType()) {
            $this->totalTtcCentimes += $ligne->getTotalCentimes();
        }
    }

    public function aDesPrixManquants(): bool
    {
        foreach ($this->lignes as $ligne) {
            if ($ligne->isPrixManquant()) {
                return true;
            }
        }

        return false;
    }
}
