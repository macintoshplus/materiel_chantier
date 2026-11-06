<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FamilleEquipement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Équipement posé sur une surface (tableau, boîte de dérivation, prise, interrupteur, point lumineux...), à la
 * position `(u, v)` du repère de la surface. `parametres` : `puissance_w` (charge de l'élément)...
 */
#[ORM\Entity]
#[ORM\Table(name: 'equipement')]
final class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 16, enumType: FamilleEquipement::class)]
    private FamilleEquipement $famille = FamilleEquipement::Electrique;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    private string $type = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    /** Position sur la surface, en mm dans son repère. */
    #[ORM\Column]
    private int $u = 0;

    #[ORM\Column]
    private int $v = 0;

    #[ORM\ManyToOne(targetEntity: Reseau::class, inversedBy: 'equipements')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Reseau $reseau = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $parametres = [];

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Piece::class, inversedBy: 'equipements')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Piece $piece,
        #[ORM\ManyToOne(targetEntity: Surface::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Surface $surface,
    ) {
        $piece->ajouterEquipement($this);
    }

    #[Assert\IsTrue(message: 'The surface must belong to the room.')]
    public function isSurfaceDeLaPiece(): bool
    {
        return $this->surface->getPiece() === $this->piece;
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getPiece(): Piece
    {
        return $this->piece;
    }

    public function getSurface(): Surface
    {
        return $this->surface;
    }

    public function getFamille(): FamilleEquipement
    {
        return $this->famille;
    }

    public function setFamille(FamilleEquipement $famille): void
    {
        $this->famille = $famille;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): void
    {
        $this->nom = '' === $nom ? null : $nom;
    }

    public function getU(): int
    {
        return $this->u;
    }

    public function getV(): int
    {
        return $this->v;
    }

    public function placer(int $u, int $v): void
    {
        $this->u = $u;
        $this->v = $v;
    }

    public function getReseau(): ?Reseau
    {
        return $this->reseau;
    }

    public function setReseau(?Reseau $reseau): void
    {
        $this->reseau = $reseau;
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

    public function getPuissanceW(): ?int
    {
        $p = $this->parametres['puissance_w'] ?? null;

        return is_numeric($p) ? (int) $p : null;
    }
}
