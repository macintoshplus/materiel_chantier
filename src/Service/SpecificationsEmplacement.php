<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Materiau\SourceCandidats;
use App\Calcul\Systeme\EmplacementCouche;
use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Catalogue\RegistreCategories;
use App\Entity\Materiau;

/**
 * Spécification d'une couche posée depuis l'écran des systèmes (ce que le moteur cherche quand l'article n'est pas
 * épinglé) et liste des articles du catalogue qui la satisfont, proposée pour l'épingler.
 */
final readonly class SpecificationsEmplacement
{
    public function __construct(private SourceCandidats $source, private RegistreCategories $categories)
    {
    }

    /**
     * Catégorie de l'emplacement ; un profilé d'ossature est un montant (une fourrure sous un plafond) dont la série
     * couvre la profondeur de la cavité isolée.
     */
    public function pour(EmplacementCouche $emplacement): MaterielSpecification
    {
        $criteres = [];
        if ('profil_ossature' === $emplacement->categorie) {
            if ('ossature.plafond' === $emplacement->calculateur) {
                $criteres[] = new Critere('famille', Operateur::Egal, 'fourrure');
            } else {
                $criteres[] = new Critere('famille', Operateur::Egal, 'montant');
                $criteres[] = new Critere('serie', Operateur::SuperieurOuEgal, '{contexte.profondeur_cavite}');
            }
        }

        return MaterielSpecification::creer($emplacement->role, $this->categories->get($emplacement->categorie), $criteres);
    }

    /**
     * Articles actifs satisfaisant la spécification, hors critères qui dépendent du calcul (inconnus avant celui-ci).
     *
     * @return list<Materiau>
     */
    public function candidats(EmplacementCouche $emplacement): array
    {
        $specification = $this->pour($emplacement);
        $statiques = array_values(array_filter($specification->criteres, static fn (Critere $c): bool => null === $c->referenceContexte()));
        $articles = [];
        foreach ($this->source->candidats(MaterielSpecification::creer($specification->role, $specification->categorie, $statiques)) as $article) {
            if ($article instanceof Materiau) {
                $articles[] = $article;
            }
        }

        return $articles;
    }
}
