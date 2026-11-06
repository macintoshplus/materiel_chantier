<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrixMateriau>
 */
final class PrixMateriauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrixMateriau::class);
    }

    /**
     * @return list<PrixMateriau> du plus récent au plus ancien
     */
    public function historique(Materiau $materiau, Fournisseur $fournisseur): array
    {
        return $this->findBy(['materiau' => $materiau, 'fournisseur' => $fournisseur], ['dateDebut' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * @return list<PrixMateriau> prix courants de l'article, un par fournisseur
     */
    public function courants(Materiau $materiau): array
    {
        return $this->findBy(['materiau' => $materiau, 'dateFin' => null]);
    }

    /**
     * @return list<PrixMateriau>
     */
    public function toutLHistorique(Materiau $materiau): array
    {
        return $this->findBy(['materiau' => $materiau], ['dateDebut' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * @return list<PrixMateriau> prix courants proposés par un fournisseur
     */
    public function vendusPar(Fournisseur $fournisseur): array
    {
        return $this->findBy(['fournisseur' => $fournisseur, 'dateFin' => null]);
    }
}
