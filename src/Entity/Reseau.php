<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FamilleEquipement;
use App\Enum\TypeCircuit;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Circuit (réseau) d'un projet : ses équipements, sa section, sa protection et, dans `parametres`, son point de
 * départ et ses liaisons : `depart` (id d'équipement), `liaisons` (liste de `{equipement, parent, conducteurs?}`) et
 * `reserve_pct`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'reseau')]
final class Reseau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $nom = '';

    #[ORM\Column(length: 16, enumType: FamilleEquipement::class)]
    private FamilleEquipement $famille = FamilleEquipement::Electrique;

    #[ORM\Column(length: 16, enumType: TypeCircuit::class)]
    private TypeCircuit $type;

    /** Section des conducteurs en mm², décimale (jamais un flottant). */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\Positive]
    private string $section;

    /** Calibre de la protection en ampères. */
    #[ORM\Column]
    #[Assert\Positive]
    private int $protection;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $parametres = [];

    /** @var Collection<int, Equipement> */
    #[ORM\OneToMany(targetEntity: Equipement::class, mappedBy: 'reseau')]
    private Collection $equipements;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Projet::class, inversedBy: 'reseaux')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Projet $projet,
        TypeCircuit $type = TypeCircuit::Eclairage,
    ) {
        $this->type = $type;
        $this->section = number_format((float) $type->sectionDefaut(), 2, '.', '');
        $this->protection = $type->protectionDefaut();
        $this->equipements = new ArrayCollection();
        $projet->ajouterReseau($this);
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getProjet(): Projet
    {
        return $this->projet;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): void
    {
        $this->nom = $nom;
    }

    public function getFamille(): FamilleEquipement
    {
        return $this->famille;
    }

    public function getType(): TypeCircuit
    {
        return $this->type;
    }

    public function setType(TypeCircuit $type): void
    {
        $this->type = $type;
    }

    public function getSection(): string
    {
        return $this->section;
    }

    public function setSection(string $section): void
    {
        $this->section = $section;
    }

    public function getProtection(): int
    {
        return $this->protection;
    }

    public function setProtection(int $protection): void
    {
        $this->protection = $protection;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParametres(): array
    {
        return $this->parametres;
    }

    /**
     * @param array<string, mixed> $parametres
     */
    public function setParametres(array $parametres): void
    {
        $this->parametres = $parametres;
    }

    /**
     * @return Collection<int, Equipement>
     */
    public function getEquipements(): Collection
    {
        return $this->equipements;
    }

    public function getDepart(): ?int
    {
        $depart = $this->parametres['depart'] ?? null;

        return is_numeric($depart) ? (int) $depart : null;
    }

    public function definirDepart(?int $equipementId): void
    {
        if (null === $equipementId) {
            unset($this->parametres['depart']);
        } else {
            $this->parametres['depart'] = $equipementId;
        }
    }

    /**
     * @return list<array{equipement: int, parent: int, conducteurs: ?int}>
     */
    public function getLiaisons(): array
    {
        $liaisons = [];
        $brut = $this->parametres['liaisons'] ?? [];
        if (!\is_array($brut)) {
            return [];
        }
        foreach ($brut as $l) {
            if (\is_array($l) && is_numeric($l['equipement'] ?? null) && is_numeric($l['parent'] ?? null)) {
                $liaisons[] = [
                    'equipement' => (int) $l['equipement'],
                    'parent' => (int) $l['parent'],
                    'conducteurs' => is_numeric($l['conducteurs'] ?? null) ? (int) $l['conducteurs'] : null,
                ];
            }
        }

        return $liaisons;
    }

    /**
     * Relie un équipement à son parent (remplace sa liaison précédente).
     */
    public function relier(int $equipementId, int $parentId, ?int $conducteurs = null): void
    {
        $liaisons = array_values(array_filter($this->getLiaisons(), static fn (array $l): bool => $l['equipement'] !== $equipementId));
        $liaisons[] = ['equipement' => $equipementId, 'parent' => $parentId, 'conducteurs' => $conducteurs];
        $this->parametres['liaisons'] = $liaisons;
    }

    public function delier(int $equipementId): void
    {
        $this->parametres['liaisons'] = array_values(array_filter($this->getLiaisons(), static fn (array $l): bool => $l['equipement'] !== $equipementId));
    }

    public function getReservePct(): ?int
    {
        $r = $this->parametres['reserve_pct'] ?? null;

        return is_numeric($r) ? (int) $r : null;
    }
}
