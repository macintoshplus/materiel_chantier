<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Materiau\SourceCandidats;
use App\Calcul\Substitution\DemandeOffre;
use App\Calcul\Substitution\OffreEquivalente;
use App\Calcul\Substitution\OffreExacte;
use App\Calcul\Substitution\ResolveurOffre;
use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;
use App\Chiffrage\SourcePrix;
use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prix du catalogue en base : l'article exact chez le fournisseur, sinon un équivalent (même spécification) qu'il
 * propose, à la date demandée (historique des prix). Jamais un autre fournisseur.
 */
final readonly class SourcePrixCatalogue implements SourcePrix
{
    /**
     * @param list<Fournisseur>                                 $fournisseurs
     * @param \Closure(MaterielLisible): ?MaterielSpecification $specification
     */
    public function __construct(
        private PrixService $prix,
        private EntityManagerInterface $em,
        private SourceCandidats $candidats,
        private array $fournisseurs,
        private \Closure $specification,
    ) {
    }

    public function fournisseurs(): array
    {
        return $this->fournisseurs;
    }

    public function prix(Materiau $article, Fournisseur $fournisseur, \DateTimeImmutable $date): ?PrixMateriau
    {
        $offres = new OffresCatalogue($this->prix, $this->em, $date);
        $resolveur = new ResolveurOffre([new OffreExacte($offres), new OffreEquivalente($this->candidats, $offres)]);
        $resultat = $resolveur->resoudre(new DemandeOffre($article, ($this->specification)($article), $fournisseur->getId() ?? 0));
        $retenu = $resultat->offre?->article;

        return $retenu instanceof Materiau ? $this->prix->prixA($retenu, $fournisseur, $date) : null;
    }
}
