<?php

declare(strict_types=1);

namespace App\Entity;

use App\Geometrie\Point2D;
use App\Geometrie\TraceCloison;
use App\Repository\CloisonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CloisonRepository::class)]
#[ORM\Table(name: 'cloison')]
final class Cloison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $nom = '';

    /** @var array{x: int, y: int} axe, mm */
    #[ORM\Column(type: Types::JSON)]
    private array $debut = ['x' => 0, 'y' => 0];

    /** @var array{x: int, y: int} axe, mm */
    #[ORM\Column(type: Types::JSON)]
    private array $fin = ['x' => 0, 'y' => 0];

    /** Dérivée du système actif, jamais saisie. */
    #[ORM\Column(nullable: true)]
    private ?int $epaisseur = null;

    /** @var array{type: string, cible: int|null, position: int|null} */
    #[ORM\Column(type: Types::JSON)]
    private array $ancrageDebut = ['type' => 'libre', 'cible' => null, 'position' => null];

    /** @var array{type: string, cible: int|null, position: int|null} */
    #[ORM\Column(type: Types::JSON)]
    private array $ancrageFin = ['type' => 'libre', 'cible' => null, 'position' => null];

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $parametres = [];

    /** @var Collection<int, Surface> faces A et B, créées et mises à jour par le code, jamais saisies */
    #[ORM\OneToMany(targetEntity: Surface::class, mappedBy: 'cloison', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $faces;

    /** @var Collection<int, Ouverture> */
    #[ORM\OneToMany(targetEntity: Ouverture::class, mappedBy: 'cloison', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ouvertures;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Piece::class, inversedBy: 'cloisons')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Piece $piece,
    ) {
        $this->faces = new ArrayCollection();
        $this->ouvertures = new ArrayCollection();
        $piece->ajouterCloison($this);
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getPiece(): Piece
    {
        return $this->piece;
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
     * @return array{x: int, y: int}
     */
    public function getDebut(): array
    {
        return $this->debut;
    }

    /**
     * @param array{x: int, y: int} $debut
     */
    public function setDebut(array $debut): void
    {
        $this->debut = $debut;
    }

    /**
     * @return array{x: int, y: int}
     */
    public function getFin(): array
    {
        return $this->fin;
    }

    /**
     * @param array{x: int, y: int} $fin
     */
    public function setFin(array $fin): void
    {
        $this->fin = $fin;
    }

    public function getEpaisseur(): ?int
    {
        return $this->epaisseur;
    }

    public function setEpaisseur(?int $epaisseur): void
    {
        $this->epaisseur = $epaisseur;
    }

    /**
     * @return array{type: string, cible: int|null, position: int|null}
     */
    public function getAncrageDebut(): array
    {
        return $this->ancrageDebut;
    }

    /**
     * @param array{type: string, cible: int|null, position: int|null} $ancrage
     */
    public function setAncrageDebut(array $ancrage): void
    {
        $this->ancrageDebut = $ancrage;
    }

    /**
     * @return array{type: string, cible: int|null, position: int|null}
     */
    public function getAncrageFin(): array
    {
        return $this->ancrageFin;
    }

    /**
     * @param array{type: string, cible: int|null, position: int|null} $ancrage
     */
    public function setAncrageFin(array $ancrage): void
    {
        $this->ancrageFin = $ancrage;
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
     * @return Collection<int, Surface>
     */
    public function getFaces(): Collection
    {
        return $this->faces;
    }

    /**
     * @return Collection<int, Ouverture>
     */
    public function getOuvertures(): Collection
    {
        return $this->ouvertures;
    }

    public function ajouterFace(Surface $face): void
    {
        if (!$this->faces->contains($face)) {
            $this->faces->add($face);
        }
    }

    public function ajouterOuverture(Ouverture $ouverture): void
    {
        if (!$this->ouvertures->contains($ouverture)) {
            $this->ouvertures->add($ouverture);
        }
    }

    public function retirerOuverture(Ouverture $ouverture): void
    {
        $this->ouvertures->removeElement($ouverture);
    }

    public function getTrace(): TraceCloison
    {
        return new TraceCloison(Point2D::depuisTableau($this->debut), Point2D::depuisTableau($this->fin));
    }

    public function definirTrace(TraceCloison $trace): void
    {
        $this->debut = $trace->debut->versTableau();
        $this->fin = $trace->fin->versTableau();
    }
}
