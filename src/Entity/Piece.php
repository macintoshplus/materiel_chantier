<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SourceMesures;
use App\Enum\TypeSurface;
use App\Geometrie\HauteurPlafond;
use App\Geometrie\HauteurPlate;
use App\Geometrie\HauteurSousPans;
use App\Geometrie\PanPlafond;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;
use App\Repository\PieceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

    /** @var Collection<int, Jonction> */
    #[ORM\OneToMany(targetEntity: Jonction::class, mappedBy: 'piece', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $jonctions;

    /** @var Collection<int, Equipement> */
    #[ORM\OneToMany(targetEntity: Equipement::class, mappedBy: 'piece', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $equipements;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Projet::class, inversedBy: 'pieces')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Projet $projet,
    ) {
        $this->surfaces = new ArrayCollection();
        $this->cloisons = new ArrayCollection();
        $this->jonctions = new ArrayCollection();
        $this->equipements = new ArrayCollection();
        $projet->ajouterPiece($this);
    }

    #[Assert\Callback]
    public function validerFormeSol(ExecutionContextInterface $context): void
    {
        if ([] !== $this->formeSol && !$this->getPolygoneSol()->estSimple()) {
            $context->buildViolation('The floor outline crosses itself.')->atPath('formeSol')->addViolation();
        }
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

    /**
     * @return Collection<int, Equipement>
     */
    public function getEquipements(): Collection
    {
        return $this->equipements;
    }

    public function ajouterEquipement(Equipement $equipement): void
    {
        if (!$this->equipements->contains($equipement)) {
            $this->equipements->add($equipement);
        }
    }

    public function retirerEquipement(Equipement $equipement): void
    {
        $this->equipements->removeElement($equipement);
    }

    /**
     * @return Collection<int, Jonction>
     */
    public function getJonctions(): Collection
    {
        return $this->jonctions;
    }

    public function ajouterJonction(Jonction $jonction): void
    {
        if (!$this->jonctions->contains($jonction)) {
            $this->jonctions->add($jonction);
        }
    }

    public function getPolygoneSol(): Polygone
    {
        return Polygone::depuisTableaux($this->formeSol);
    }

    public function getSol(): ?Surface
    {
        foreach ($this->surfaces as $surface) {
            if (TypeSurface::Sol === $surface->getType()) {
                return $surface;
            }
        }

        return null;
    }

    /**
     * @return list<Surface>
     */
    public function getMurs(): array
    {
        return $this->surfacesDeType(TypeSurface::Mur);
    }

    /**
     * Pans de plafond (un plafond plat est un pan horizontal).
     *
     * @return list<Surface>
     */
    public function getPans(): array
    {
        return $this->surfacesDeType(TypeSurface::PlafondPan);
    }

    /**
     * @return list<Surface>
     */
    private function surfacesDeType(TypeSurface $type): array
    {
        return array_values(array_filter($this->surfaces->toArray(), static fn (Surface $s): bool => $type === $s->getType()));
    }

    /**
     * Plafond de la pièce pour les cloisons : les pans s'il y en a, sinon la hauteur sous plafond par défaut.
     */
    public function hauteurPlafond(): HauteurPlafond
    {
        $pans = $this->getPans();
        if ([] !== $pans) {
            return new HauteurSousPans(array_map(
                static fn (Surface $s): PanPlafond => new PanPlafond($s->cle(), $s->getPlan(), $s->getContour()->polygone()),
                $pans,
            ));
        }
        if (null !== $this->hauteurSousPlafond) {
            return new HauteurPlate($this->hauteurSousPlafond);
        }

        return new HauteurSousPans([]);
    }

    /**
     * Hauteur du plafond à la verticale d'un point de la pièce ; null si aucun plafond n'est défini.
     */
    public function hauteurA(Point2D $point): ?int
    {
        return $this->hauteurPlafond()->hauteurA($point);
    }
}
