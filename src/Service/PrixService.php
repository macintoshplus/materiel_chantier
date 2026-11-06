<?php

declare(strict_types=1);

namespace App\Service;

use App\Catalogue\HistoriquePrix;
use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;
use App\Repository\PrixMateriauRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PrixService
{
    private readonly HistoriquePrix $historique;

    public function __construct(
        private readonly PrixMateriauRepository $prix,
        private readonly EntityManagerInterface $em,
    ) {
        $this->historique = new HistoriquePrix();
    }

    public function prixA(Materiau $materiau, Fournisseur $fournisseur, \DateTimeImmutable $date): ?PrixMateriau
    {
        return $this->historique->prixA($this->prix->historique($materiau, $fournisseur), $date);
    }

    /**
     * Enregistre un nouveau prix et clôt le précédent.
     */
    public function definirPrix(
        Materiau $materiau,
        Fournisseur $fournisseur,
        string $prixLotTtc,
        \DateTimeImmutable $debut,
        ?int $unitesParLot = null,
        string $source = PrixMateriau::SOURCE_MANUEL,
        ?string $referenceFournisseur = null,
    ): PrixMateriau {
        $nouveau = new PrixMateriau($materiau, $fournisseur, $prixLotTtc, $debut, $unitesParLot, $source);
        $nouveau->setReferenceFournisseur($referenceFournisseur);
        $this->historique->definir($this->prix->historique($materiau, $fournisseur), $nouveau);
        $this->em->persist($nouveau);
        $this->em->flush();

        return $nouveau;
    }
}
