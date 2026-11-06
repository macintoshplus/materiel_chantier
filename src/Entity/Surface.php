<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CoteCloison;
use App\Enum\TypeSurface;
use App\Repository\SurfaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Table unique : un mur, un pan incliné, un plafond, un sol et une face de cloison sont le même objet,
 * `type` est indicatif. Les vecteurs sont des entiers non normalisés (aucun flottant persisté).
 */
#[ORM\Entity(repositoryClass: SurfaceRepository::class)]
#[ORM\Table(name: 'surface')]
final class Surface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Cloison::class, inversedBy: 'faces')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Cloison $cloison = null;

    #[ORM\Column(length: 1, nullable: true, enumType: CoteCloison::class)]
    private ?CoteCloison $cote = null;

    #[ORM\Column(length: 255)]
    private string $nom = '';

    /** @var list<int> */
    #[ORM\Column(name: 'origine_3d', type: Types::JSON)]
    private array $origine3D = [0, 0, 0];

    /** @var list<int> */
    #[ORM\Column(type: Types::JSON)]
    private array $vecteurU = [1000, 0, 0];

    /** @var list<int> */
    #[ORM\Column(type: Types::JSON)]
    private array $vecteurV = [0, 0, 1000];

    /** @var list<array{id: string, u: int, v: int}> */
    #[ORM\Column(type: Types::JSON)]
    private array $polygone = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $contraintes = [];

    /** @var Collection<int, Ouverture> */
    #[ORM\OneToMany(targetEntity: Ouverture::class, mappedBy: 'surface', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ouvertures;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Piece::class, inversedBy: 'surfaces')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Piece $piece,
        #[ORM\Column(length: 16, enumType: TypeSurface::class)]
        private TypeSurface $type,
    ) {
        $this->ouvertures = new ArrayCollection();
        $piece->ajouterSurface($this);
    }

    #[Assert\IsTrue(message: 'A partition face requires a partition and a side; other surfaces must have neither.')]
    public function isRattachementCloisonValide(): bool
    {
        if (TypeSurface::CloisonFace === $this->type) {
            return null !== $this->cloison && null !== $this->cote;
        }

        return null === $this->cloison && null === $this->cote;
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getPiece(): Piece
    {
        return $this->piece;
    }

    public function getType(): TypeSurface
    {
        return $this->type;
    }

    public function setType(TypeSurface $type): void
    {
        $this->type = $type;
    }

    public function getCloison(): ?Cloison
    {
        return $this->cloison;
    }

    public function getCote(): ?CoteCloison
    {
        return $this->cote;
    }

    public function rattacherACloison(?Cloison $cloison, ?CoteCloison $cote): void
    {
        $this->cloison = $cloison;
        $this->cote = $cote;
        $cloison?->ajouterFace($this);
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): void
    {
        $this->nom = $nom;
    }

    /**
     * @return list<int>
     */
    public function getOrigine3D(): array
    {
        return $this->origine3D;
    }

    /**
     * @param list<int> $origine3D
     */
    public function setOrigine3D(array $origine3D): void
    {
        $this->origine3D = $origine3D;
    }

    /**
     * @return list<int>
     */
    public function getVecteurU(): array
    {
        return $this->vecteurU;
    }

    /**
     * @param list<int> $vecteurU
     */
    public function setVecteurU(array $vecteurU): void
    {
        $this->vecteurU = $vecteurU;
    }

    /**
     * @return list<int>
     */
    public function getVecteurV(): array
    {
        return $this->vecteurV;
    }

    /**
     * @param list<int> $vecteurV
     */
    public function setVecteurV(array $vecteurV): void
    {
        $this->vecteurV = $vecteurV;
    }

    /**
     * @return list<array{id: string, u: int, v: int}>
     */
    public function getPolygone(): array
    {
        return $this->polygone;
    }

    /**
     * @param list<array{id: string, u: int, v: int}> $polygone
     */
    public function setPolygone(array $polygone): void
    {
        $this->polygone = $polygone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContraintes(): array
    {
        return $this->contraintes;
    }

    /**
     * @param array<string, mixed> $contraintes
     */
    public function setContraintes(array $contraintes): void
    {
        $this->contraintes = $contraintes;
    }

    /**
     * @return Collection<int, Ouverture>
     */
    public function getOuvertures(): Collection
    {
        return $this->ouvertures;
    }

    public function ajouterOuverture(Ouverture $ouverture): void
    {
        if (!$this->ouvertures->contains($ouverture)) {
            $this->ouvertures->add($ouverture);
        }
    }
}
