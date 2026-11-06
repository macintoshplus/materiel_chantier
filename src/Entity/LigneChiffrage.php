<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TypeLigneChiffrage;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne d'un chiffrage : copie figée de l'article (référence, libellé, dimensions), du fournisseur (nom, adresse), des
 * quantités et du prix. Les clés étrangères vers pièce, surface et fournisseur sont informatives (vidées à la
 * suppression) ; `materiauId` et `prixMateriauId` ne servent qu'à la traçabilité. Montants en centimes, marge en
 * centièmes de pourcent, quantité avec marge en millièmes d'unité, taux de perte en centièmes de pourcent.
 *
 * Une ligne `repartition` porte la part d'une pièce (ou surface) dans un achat agrégé : quantités et total répartis
 * proportionnellement au besoin, sans nombre de lots.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ligne_chiffrage')]
#[ORM\Index(name: 'idx_ligne_chiffrage_type', columns: ['chiffrage_id', 'type'])]
final class LigneChiffrage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Chiffrage::class, inversedBy: 'lignes')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Chiffrage $chiffrage,
        #[ORM\Column(length: 16, enumType: TypeLigneChiffrage::class)]
        private TypeLigneChiffrage $type,
        #[ORM\ManyToOne(targetEntity: Piece::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Piece $piece = null,
        #[ORM\ManyToOne(targetEntity: Surface::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Surface $surface = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $pieceNom = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $surfaceNom = null,
        #[ORM\Column(length: 48, nullable: true)]
        private ?string $categorie = null,
        #[ORM\Column(nullable: true)]
        private ?int $materiauId = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $materiauReference = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $materiauLibelle = null,
        #[ORM\Column(nullable: true)]
        private ?int $longueur = null,
        #[ORM\Column(nullable: true)]
        private ?int $largeur = null,
        #[ORM\Column(nullable: true)]
        private ?int $epaisseur = null,
        #[ORM\Column(nullable: true)]
        private ?int $hauteur = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $referenceInitiale = null,
        #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Fournisseur $fournisseur = null,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $fournisseurNom = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $fournisseurAdresse = null,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $referenceFournisseur = null,
        #[ORM\Column(options: ['default' => 0])]
        private int $quantiteBesoin = 0,
        #[ORM\Column(options: ['default' => 0])]
        private int $margeCentiemes = 0,
        #[ORM\Column(options: ['default' => 0])]
        private int $quantiteAvecMargeMilli = 0,
        #[ORM\Column(options: ['default' => 1])]
        private int $unitesParLot = 1,
        #[ORM\Column(options: ['default' => 0])]
        private int $nombreLots = 0,
        #[ORM\Column(options: ['default' => 0])]
        private int $quantiteAchetee = 0,
        #[ORM\Column(nullable: true)]
        private ?int $prixLotCentimes = null,
        #[ORM\Column(options: ['default' => 0])]
        private int $totalCentimes = 0,
        #[ORM\Column(options: ['default' => 0])]
        private int $chute = 0,
        #[ORM\Column(options: ['default' => 0])]
        private int $tauxPerteCentiemes = 0,
        #[ORM\Column(nullable: true)]
        private ?int $prixMateriauId = null,
        #[ORM\Column(options: ['default' => false])]
        private bool $prixManquant = false,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getChiffrage(): Chiffrage
    {
        return $this->chiffrage;
    }

    public function getType(): TypeLigneChiffrage
    {
        return $this->type;
    }

    public function getPiece(): ?Piece
    {
        return $this->piece;
    }

    public function getSurface(): ?Surface
    {
        return $this->surface;
    }

    public function getPieceNom(): ?string
    {
        return $this->pieceNom;
    }

    public function getSurfaceNom(): ?string
    {
        return $this->surfaceNom;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function getMateriauId(): ?int
    {
        return $this->materiauId;
    }

    public function getMateriauReference(): ?string
    {
        return $this->materiauReference;
    }

    public function getMateriauLibelle(): ?string
    {
        return $this->materiauLibelle;
    }

    public function getLongueur(): ?int
    {
        return $this->longueur;
    }

    public function getLargeur(): ?int
    {
        return $this->largeur;
    }

    public function getEpaisseur(): ?int
    {
        return $this->epaisseur;
    }

    public function getHauteur(): ?int
    {
        return $this->hauteur;
    }

    public function getReferenceInitiale(): ?string
    {
        return $this->referenceInitiale;
    }

    public function getFournisseur(): ?Fournisseur
    {
        return $this->fournisseur;
    }

    public function getFournisseurNom(): ?string
    {
        return $this->fournisseurNom;
    }

    public function getFournisseurAdresse(): ?string
    {
        return $this->fournisseurAdresse;
    }

    public function getReferenceFournisseur(): ?string
    {
        return $this->referenceFournisseur;
    }

    public function getQuantiteBesoin(): int
    {
        return $this->quantiteBesoin;
    }

    public function getMargeCentiemes(): int
    {
        return $this->margeCentiemes;
    }

    public function getQuantiteAvecMargeMilli(): int
    {
        return $this->quantiteAvecMargeMilli;
    }

    public function getUnitesParLot(): int
    {
        return $this->unitesParLot;
    }

    public function getNombreLots(): int
    {
        return $this->nombreLots;
    }

    public function getQuantiteAchetee(): int
    {
        return $this->quantiteAchetee;
    }

    public function getPrixLotCentimes(): ?int
    {
        return $this->prixLotCentimes;
    }

    public function getTotalCentimes(): int
    {
        return $this->totalCentimes;
    }

    public function getChute(): int
    {
        return $this->chute;
    }

    public function getTauxPerteCentiemes(): int
    {
        return $this->tauxPerteCentiemes;
    }

    public function getPrixMateriauId(): ?int
    {
        return $this->prixMateriauId;
    }

    public function isPrixManquant(): bool
    {
        return $this->prixManquant;
    }
}
