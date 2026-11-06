<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypePose;
use App\Repository\OuvertureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Portée par une surface OU par une cloison (repère du tracé, projetée sur les deux faces), jamais les deux.
 */
#[ORM\Entity(repositoryClass: OuvertureRepository::class)]
#[ORM\Table(name: 'ouverture')]
final class Ouverture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    private string $type = 'fenetre';

    /** Rectangle englobant, en mm dans le repère de la surface. */
    #[ORM\Column]
    private int $u = 0;

    #[ORM\Column]
    private int $v = 0;

    #[ORM\Column]
    #[Assert\Positive]
    private int $largeur = 1;

    #[ORM\Column]
    #[Assert\Positive]
    private int $hauteur = 1;

    /** @var list<array{u: int, v: int}>|null contour non rectangulaire */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $polygone = null;

    #[ORM\Column(length: 16, enumType: TypePose::class)]
    private TypePose $typePose = TypePose::Applique;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $typeContour = null;

    /** Profondeur du dormant en retrait du nu du mur, en mm. */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $refoncement = 0;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $parametres = [];

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Surface::class, inversedBy: 'ouvertures')]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Surface $surface = null,
        #[ORM\ManyToOne(targetEntity: Cloison::class, inversedBy: 'ouvertures')]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Cloison $cloison = null,
    ) {
        $surface?->ajouterOuverture($this);
        $cloison?->ajouterOuverture($this);
    }

    #[Assert\IsTrue(message: 'An opening belongs to exactly one surface or one partition.')]
    public function isUnSeulParent(): bool
    {
        return (null === $this->surface) !== (null === $this->cloison);
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): void
    {
        $this->nom = $nom;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function definirRectangle(int $u, int $v, int $largeur, int $hauteur): void
    {
        $this->u = $u;
        $this->v = $v;
        $this->largeur = $largeur;
        $this->hauteur = $hauteur;
    }

    public function getU(): int
    {
        return $this->u;
    }

    public function getV(): int
    {
        return $this->v;
    }

    public function getLargeur(): int
    {
        return $this->largeur;
    }

    public function getHauteur(): int
    {
        return $this->hauteur;
    }

    /**
     * @return list<array{u: int, v: int}>|null
     */
    public function getPolygone(): ?array
    {
        return $this->polygone;
    }

    /**
     * @param list<array{u: int, v: int}>|null $polygone
     */
    public function setPolygone(?array $polygone): void
    {
        $this->polygone = $polygone;
    }

    public function getTypePose(): TypePose
    {
        return $this->typePose;
    }

    public function setTypePose(TypePose $typePose): void
    {
        $this->typePose = $typePose;
    }

    public function getTypeContour(): ?string
    {
        return $this->typeContour;
    }

    public function setTypeContour(?string $typeContour): void
    {
        $this->typeContour = $typeContour;
    }

    public function getRefoncement(): int
    {
        return $this->refoncement;
    }

    public function setRefoncement(int $refoncement): void
    {
        $this->refoncement = $refoncement;
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
}
