<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypeJonction;
use App\Repository\JonctionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jonction entre deux surfaces, exprimée par des identifiants de sommets stables (jamais des coordonnées).
 * Côté B : `surfaceB` est null pour le sol et les jonctions sans cible (cloison sur le sol) ; les identifiants B
 * sont null quand la cible ne partage pas de sommets (pan sous lequel passe une cloison). Une cloison s'appuie
 * sur une portion de l'arête cible : `debutMmB` / `finMmB` (mm depuis le premier sommet B).
 */
#[ORM\Entity(repositoryClass: JonctionRepository::class)]
#[ORM\Table(name: 'jonction')]
final class Jonction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 32, enumType: TypeJonction::class)]
    private TypeJonction $type;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $sommetDebutB = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $sommetFinB = null;

    #[ORM\Column(nullable: true)]
    private ?int $debutMmB = null;

    #[ORM\Column(nullable: true)]
    private ?int $finMmB = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Piece::class, inversedBy: 'jonctions')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Piece $piece,
        #[ORM\ManyToOne(targetEntity: Surface::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Surface $surfaceA,
        #[ORM\Column(length: 32)]
        private string $sommetDebutA,
        #[ORM\Column(length: 32)]
        private string $sommetFinA,
        TypeJonction $type,
        #[ORM\ManyToOne(targetEntity: Surface::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Surface $surfaceB = null,
    ) {
        $this->type = $type;
        $piece->ajouterJonction($this);
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getPiece(): Piece
    {
        return $this->piece;
    }

    public function getSurfaceA(): Surface
    {
        return $this->surfaceA;
    }

    public function getSurfaceB(): ?Surface
    {
        return $this->surfaceB;
    }

    public function getSommetDebutA(): string
    {
        return $this->sommetDebutA;
    }

    public function getSommetFinA(): string
    {
        return $this->sommetFinA;
    }

    public function getSommetDebutB(): ?string
    {
        return $this->sommetDebutB;
    }

    public function getSommetFinB(): ?string
    {
        return $this->sommetFinB;
    }

    public function getDebutMmB(): ?int
    {
        return $this->debutMmB;
    }

    public function getFinMmB(): ?int
    {
        return $this->finMmB;
    }

    public function getType(): TypeJonction
    {
        return $this->type;
    }

    public function definirType(TypeJonction $type): void
    {
        $this->type = $type;
    }

    public function definirCoteB(?string $debut, ?string $fin, ?int $debutMm = null, ?int $finMm = null): void
    {
        $this->sommetDebutB = $debut;
        $this->sommetFinB = $fin;
        $this->debutMmB = $debutMm;
        $this->finMmB = $finMm;
    }

    /**
     * Clé d'identité : mêmes surfaces et mêmes arêtes (dans les deux sens pour les arêtes partagées entre murs).
     */
    public function identite(): string
    {
        return self::cleIdentite($this->surfaceA, $this->sommetDebutA, $this->sommetFinA, $this->surfaceB);
    }

    public static function cleIdentite(Surface $a, string $debut, string $fin, ?Surface $b): string
    {
        return $a->cle().'|'.$debut.'|'.$fin.'|'.($b?->cle() ?? '-');
    }
}
