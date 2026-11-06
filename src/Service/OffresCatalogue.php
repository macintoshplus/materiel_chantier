<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Substitution\Offre;
use App\Calcul\Substitution\SourceOffres;
use App\Catalogue\MaterielLisible;
use App\Entity\Fournisseur;
use App\Entity\Materiau;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Offres d'un fournisseur d'après l'historique des prix : un article est « proposé » s'il a un prix valide à la date.
 * La taille de lot du prix, quand elle diffère de celle de l'article, est portée par l'offre.
 */
final class OffresCatalogue implements SourceOffres
{
    /** @var array<int, Fournisseur|null> */
    private array $fournisseurs = [];

    public function __construct(
        private readonly PrixService $prix,
        private readonly EntityManagerInterface $em,
        private readonly \DateTimeImmutable $date,
    ) {
    }

    public function offre(MaterielLisible $article, int $fournisseur): ?Offre
    {
        if (!$article instanceof Materiau) {
            return null;
        }
        $this->fournisseurs[$fournisseur] ??= $this->em->find(Fournisseur::class, $fournisseur);
        $entite = $this->fournisseurs[$fournisseur];
        if (null === $entite) {
            return null;
        }
        $prix = $this->prix->prixA($article, $entite, $this->date);
        if (null === $prix) {
            return null;
        }
        $lot = $prix->unitesParLotEffectif();

        return new Offre($article, $fournisseur, $lot !== $article->getUnitesParLot() ? $lot : null);
    }
}
