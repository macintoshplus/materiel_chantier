<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Chiffrage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chiffrage>
 */
final class ChiffrageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chiffrage::class);
    }
}
