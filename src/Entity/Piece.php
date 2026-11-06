<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SourceMesures;
use App\Repository\PieceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PieceRepository::class)]
#[ORM\Table(name: 'piece')]
final class Piece
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $nom = '';

    /** Valeur par défaut pour créer des murs rectangulaires, en mm : pas une source de vérité. */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $hauteurSousPlafond = null;

    /** @var list<array{x: int, y: int}> sommets du sol en mm, anti-horaire */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\All([new Assert\Collection(fields: ['x' => new Assert\Type('int'), 'y' => new Assert\Type('int')])])]
    private array $formeSol = [];

    #[ORM\Column(length: 16, enumType: SourceMesures::class)]
    private SourceMesures $sourceMesures = SourceMesures::Saisie;

    /** @var Collection<int, Surface> */
    #[ORM\OneToMany(targetEntity: Surface::class, mappedBy: 'piece', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $surfaces;

    /** @var Collection<int, Cloison> */
    #[ORM\OneToMany(targetEntity: Cloison::class, mappedBy: 'piece', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $cloisons;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Projet::class, inversedBy: 'pieces')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Projet $projet,
    ) {
        $this->surfaces = new ArrayCollection();
        $this->cloisons = new ArrayCollection();
        $projet->ajouterPiece($this);
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

    public function getHauteurSousPlafond(): ?int
    {
        return $this->hauteurSousPlafond;
    }

    public function setHauteurSousPlafond(?int $hauteurSousPlafond): void
    {
        $this->hauteurSousPlafond = $hauteurSousPlafond;
    }

    /**
     * @return list<array{x: int, y: int}>
     */
    public function getFormeSol(): array
    {
        return $this->formeSol;
    }

    /**
     * @param list<array{x: int, y: int}> $formeSol
     */
    public function setFormeSol(array $formeSol): void
    {
        $this->formeSol = $formeSol;
    }

    public function getSourceMesures(): SourceMesures
    {
        return $this->sourceMesures;
    }

    public function setSourceMesures(SourceMesures $sourceMesures): void
    {
        $this->sourceMesures = $sourceMesures;
    }

    /**
     * @return Collection<int, Surface>
     */
    public function getSurfaces(): Collection
    {
        return $this->surfaces;
    }

    /**
     * @return Collection<int, Cloison>
     */
    public function getCloisons(): Collection
    {
        return $this->cloisons;
    }

    public function ajouterSurface(Surface $surface): void
    {
        if (!$this->surfaces->contains($surface)) {
            $this->surfaces->add($surface);
        }
    }

    public function ajouterCloison(Cloison $cloison): void
    {
        if (!$this->cloisons->contains($cloison)) {
            $this->cloisons->add($cloison);
        }
    }
}
