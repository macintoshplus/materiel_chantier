<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CoteCloison;
use App\Enum\TypeSurface;
use App\Geometrie\ConstructeurSurface;
use App\Geometrie\ContourIdentifie;
use App\Geometrie\ContrainteSommet;
use App\Geometrie\Direction;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\MessagesGeometrie;
use App\Geometrie\Plan;
use App\Geometrie\Segment;
use App\Geometrie\SurfaceGeneree;
use App\Geometrie\SurfaceGeometrique;
use App\Repository\SurfaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

    #[Assert\Callback]
    public function validerContraintes(ExecutionContextInterface $context): void
    {
        $contraintes = $this->getContraintesSommets();
        if ([] === $contraintes) {
            return;
        }
        try {
            (new ConstructeurSurface())->construire($contraintes);
        } catch (ErreurGeometrique $e) {
            $violation = $context->buildViolation(MessagesGeometrie::message($e->erreur))->atPath('contraintes');
            foreach ($e->params as $nom => $valeur) {
                $violation->setParameter('{{ '.$nom.' }}', (string) $valeur);
            }
            $violation->addViolation();
        }
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

    /**
     * Applique le plan, le contour, les contraintes et (mur) l'arête du sol produits par un générateur.
     */
    public function appliquerGeneree(SurfaceGeneree $generee): void
    {
        $this->definirPlan($generee->plan);
        $this->definirContour($generee->contour);
        $this->definirContraintesSommets($generee->contraintes);
        $this->definirAreteSol($generee->areteSol);
    }

    public function retirerOuverture(Ouverture $ouverture): void
    {
        $this->ouvertures->removeElement($ouverture);
    }

    /**
     * Clé opaque de la surface pour la bibliothèque géométrique (valable le temps de la requête).
     */
    public function cle(): string
    {
        return 's'.spl_object_id($this);
    }

    /**
     * Polygone `(u, v)` avec ses identifiants de sommets stables.
     */
    public function getContour(): ContourIdentifie
    {
        return ContourIdentifie::depuisTableau($this->polygone);
    }

    public function definirContour(ContourIdentifie $contour): void
    {
        $this->polygone = $contour->versTableau();
    }

    public function getPlan(): Plan
    {
        return Plan::depuisListes($this->origine3D, $this->vecteurU, $this->vecteurV);
    }

    public function definirPlan(Plan $plan): void
    {
        $this->origine3D = $plan->origine->versListe();
        $this->vecteurU = $plan->u->versListe();
        $this->vecteurV = $plan->v->versListe();
    }

    /**
     * Contraintes de sommets (stockées sous la clé `sommets` ; les autres clés de `contraintes` sont conservées).
     *
     * @return list<ContrainteSommet>
     */
    public function getContraintesSommets(): array
    {
        $liste = $this->contraintes['sommets'] ?? [];
        if (!\is_array($liste)) {
            return [];
        }
        $contraintes = [];
        foreach ($liste as $t) {
            if (\is_array($t)) {
                $contraintes[] = ContrainteSommet::depuisTableau($t);
            }
        }

        return $contraintes;
    }

    /**
     * @param list<ContrainteSommet> $contraintes
     */
    public function definirContraintesSommets(array $contraintes): void
    {
        $this->contraintes['sommets'] = array_map(static fn (ContrainteSommet $c): array => $c->versTableau(), $contraintes);
    }

    /**
     * Pour un mur : arête du sol d'origine, par identifiants de sommets du sol.
     *
     * @return array{debut: string, fin: string}|null
     */
    public function getAreteSol(): ?array
    {
        $a = $this->contraintes['arete_sol'] ?? null;
        if (\is_array($a) && \is_string($a['debut'] ?? null) && \is_string($a['fin'] ?? null)) {
            return ['debut' => $a['debut'], 'fin' => $a['fin']];
        }

        return null;
    }

    /**
     * @param array{debut: string, fin: string}|null $arete
     */
    public function definirAreteSol(?array $arete): void
    {
        if (null === $arete) {
            unset($this->contraintes['arete_sol']);
        } else {
            $this->contraintes['arete_sol'] = $arete;
        }
    }

    /**
     * Géométrie pure de la surface, ouvertures comprises.
     */
    public function geometrie(): SurfaceGeometrique
    {
        $formes = [];
        foreach ($this->ouvertures as $ouverture) {
            $formes[] = $ouverture->getForme();
        }

        return new SurfaceGeometrique($this->getPlan(), $this->getContour(), $formes);
    }

    /**
     * Éléments d'ossature (voir SurfaceGeometrique::coupes).
     *
     * @return list<Segment>
     */
    public function coupes(Direction $direction, int $pas): array
    {
        return $this->geometrie()->coupes($direction, $pas);
    }
}
