<?php

declare(strict_types=1);

namespace App\Repository;

use App\Calcul\Materiau\SourceCandidats;
use App\Catalogue\MaterielSpecification;
use App\Entity\Materiau;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Materiau>
 */
final class MateriauRepository extends ServiceEntityRepository implements SourceCandidats
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Materiau::class);
    }

    /**
     * Articles actifs satisfaisant une spécification (déjà résolue). La catégorie filtre en SQL,
     * les critères portent sur le JSON et sont évalués en PHP.
     *
     * @return list<Materiau>
     */
    public function candidats(MaterielSpecification $specification): array
    {
        $articles = $this->findBy(['categorie' => $specification->categorie->code(), 'actif' => true], ['reference' => 'ASC']);

        return array_values(array_filter($articles, static fn (Materiau $m): bool => $specification->estSatisfaitePar($m)));
    }

    /**
     * @return list<Materiau>
     */
    public function lister(?string $categorie): array
    {
        return null === $categorie || '' === $categorie
            ? $this->findBy([], ['categorie' => 'ASC', 'reference' => 'ASC'])
            : $this->findBy(['categorie' => $categorie], ['reference' => 'ASC']);
    }

    public function parReference(string $reference): ?Materiau
    {
        return $this->findOneBy(['reference' => $reference]);
    }
}
