<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PrixMateriauRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un prix n'est jamais modifié : un nouveau prix clôt le précédent (voir HistoriquePrix).
 */
#[ORM\Entity(repositoryClass: PrixMateriauRepository::class)]
#[ORM\Table(name: 'prix_materiau')]
#[ORM\Index(name: 'idx_prix_materiau_fournisseur', columns: ['materiau_id', 'fournisseur_id'])]
final class PrixMateriau
{
    public const string SOURCE_MANUEL = 'manuel';
    public const string SOURCE_IMPORT = 'import';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $referenceFournisseur = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Materiau::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Materiau $materiau,
        #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Fournisseur $fournisseur,
        #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
        private string $prixLotTtc,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $dateDebut,
        #[ORM\Column(nullable: true)]
        private ?int $unitesParLot = null,
        #[ORM\Column(length: 16)]
        private string $source = self::SOURCE_MANUEL,
        #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2, nullable: true)]
        private ?string $tauxTva = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id ?? null;
    }

    public function getMateriau(): Materiau
    {
        return $this->materiau;
    }

    public function getFournisseur(): Fournisseur
    {
        return $this->fournisseur;
    }

    public function getPrixLotTtc(): string
    {
        return $this->prixLotTtc;
    }

    public function getUnitesParLot(): ?int
    {
        return $this->unitesParLot;
    }

    /**
     * Taille de lot effective : la surcharge du prix, sinon la valeur par défaut de l'article.
     */
    public function unitesParLotEffectif(): int
    {
        return $this->unitesParLot ?? $this->materiau->getUnitesParLot();
    }

    public function getTauxTva(): ?string
    {
        return $this->tauxTva;
    }

    public function getDateDebut(): \DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getReferenceFournisseur(): ?string
    {
        return $this->referenceFournisseur;
    }

    public function setReferenceFournisseur(?string $referenceFournisseur): void
    {
        $this->referenceFournisseur = $referenceFournisseur;
    }

    public function estCourant(): bool
    {
        return null === $this->dateFin;
    }

    public function estValideA(\DateTimeImmutable $date): bool
    {
        return $this->dateDebut <= $date && (null === $this->dateFin || $date < $this->dateFin);
    }

    public function cloturer(\DateTimeImmutable $date): void
    {
        if (null !== $this->dateFin) {
            throw new \LogicException('This price is already closed.');
        }
        if ($date < $this->dateDebut) {
            throw new \InvalidArgumentException('A price cannot end before it starts.');
        }
        $this->dateFin = $date;
    }
}
