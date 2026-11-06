<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Agregation\AgregateurMateriaux;
use App\Calcul\Agregation\BesoinsProjet;
use App\Calcul\Agregation\ConfigurationMarges;
use App\Calcul\Agregation\ResolveurMarge;
use App\Calcul\Materiau\SourceCandidats;
use App\Calcul\Materiau\SpecificationSerialiseur;
use App\Calcul\Substitution\ResolveurOffre;
use App\Calcul\Substitution\SubstitutionArticle;
use App\Calcul\Substitution\SubstitutionFournisseur;
use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\RegistreCategories;
use App\Entity\Cloison;
use App\Entity\Fournisseur;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * « Calculer » un projet : le système actif de chaque surface et cloison est calculé, puis les besoins bruts sont
 * agrégés (coupes réunies, marge de sécurité, lots entiers), par projet, par pièce ou par surface. Avec un fournisseur,
 * l'article de chaque besoin est d'abord remplacé par l'offre de ce fournisseur (équivalent ou prix manquant).
 */
final class CalculProjet
{
    public function __construct(
        private readonly CalculSysteme $calcul,
        private readonly RegistreCategories $categories,
        private readonly SourceCandidats $candidats,
        private readonly SpecificationSerialiseur $serialiseur,
        private readonly PrixService $prix,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function calculer(Projet $projet, PorteeAgregation $portee = PorteeAgregation::Projet, ?Fournisseur $fournisseur = null, ?\DateTimeImmutable $date = null): ResultatCalculProjet
    {
        $agregateur = new AgregateurMateriaux(new ResolveurMarge($this->categories, ConfigurationMarges::depuis($projet->getParametres())));
        $avertissements = [];
        $erreurs = [];
        /** @var array<string, array{nom: string, besoins: BesoinsProjet}> $groupes */
        $groupes = [];
        /** @var array<int, MaterielSpecification> $specifications */
        $specifications = [];

        foreach ($projet->getPieces() as $piece) {
            foreach ($this->supports($piece) as $support) {
                $systeme = null;
                foreach ($support->getSystemes() as $candidat) {
                    if ($candidat->isActif()) {
                        $systeme = $candidat;
                    }
                }
                if (null === $systeme) {
                    continue;
                }
                $resultat = $this->calcul->calculerSysteme($systeme);
                foreach ($resultat->avertissements as $a) {
                    $avertissements[json_encode([$a->code, $a->params], \JSON_THROW_ON_ERROR)] = $a;
                }
                foreach ($resultat->erreurs as $e) {
                    $erreurs[json_encode([$e->code, $e->params], \JSON_THROW_ON_ERROR)] = $e;
                }
                foreach ($systeme->getCouches() as $couche) {
                    $materiau = $couche->getMateriau();
                    $donnees = $couche->getSpecification();
                    if (null !== $materiau && null !== $donnees) {
                        $specifications[spl_object_id($materiau)] = $this->serialiseur->depuis($donnees);
                    }
                }

                [$cle, $nom] = match ($portee) {
                    PorteeAgregation::Projet => ['projet', $projet->getNom()],
                    PorteeAgregation::Piece => ['p'.spl_object_id($piece), $piece->getNom()],
                    PorteeAgregation::Surface => ['s'.spl_object_id($support), $piece->getNom().' / '.$support->getNom()],
                };
                $groupes[$cle]['nom'] = $nom;
                $groupes[$cle]['besoins'] ??= new BesoinsProjet();
                $groupes[$cle]['besoins']->ajouter(array_map(static fn ($l) => $l->ligne, $resultat->lignes), $piece->getNom().' / '.$support->getNom());
            }
        }

        $substitution = null !== $fournisseur ? $this->substitution($fournisseur, $date ?? new \DateTimeImmutable(), static fn (MaterielLisible $a): ?MaterielSpecification => $specifications[spl_object_id($a)] ?? null) : null;

        $resultats = [];
        foreach ($groupes as $groupe) {
            $resultats[] = new GroupeAchats($groupe['nom'], $agregateur->calculer($groupe['besoins'], $substitution));
        }

        return new ResultatCalculProjet($portee, $resultats, array_values($avertissements), array_values($erreurs));
    }

    /**
     * @param \Closure(MaterielLisible): ?MaterielSpecification $specification
     */
    private function substitution(Fournisseur $fournisseur, \DateTimeImmutable $date, \Closure $specification): SubstitutionArticle
    {
        $offres = new OffresCatalogue($this->prix, $this->em, $date);

        return new SubstitutionFournisseur(ResolveurOffre::standard($offres, $this->candidats), $fournisseur->getId() ?? 0, $specification);
    }

    /**
     * @return list<Surface|Cloison>
     */
    private function supports(Piece $piece): array
    {
        $supports = [];
        foreach ($piece->getSurfaces() as $surface) {
            if (null === $surface->getCloison()) {
                $supports[] = $surface;
            }
        }
        foreach ($piece->getCloisons() as $cloison) {
            $supports[] = $cloison;
        }

        return $supports;
    }
}
