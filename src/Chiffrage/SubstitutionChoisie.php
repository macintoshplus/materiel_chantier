<?php

declare(strict_types=1);

namespace App\Chiffrage;

use App\Calcul\Substitution\NatureOffre;
use App\Calcul\Substitution\Offre;
use App\Calcul\Substitution\ResolveurOffre;
use App\Calcul\Substitution\ResultatOffre;
use App\Calcul\Substitution\SubstitutionArticle;
use App\Catalogue\MaterielLisible;
use App\Entity\PrixMateriau;
use App\Geometrie\Avertissement;

/**
 * Substitution déjà décidée par la stratégie : l'article du prix retenu (avec la taille de lot de ce prix), ou « prix
 * manquant » pour un article sans prix. Le moteur d'agrégation optimise les coupes avec les dimensions de cet article.
 */
final readonly class SubstitutionChoisie implements SubstitutionArticle
{
    /**
     * @param array<int, PrixMateriau|null> $choix prix retenu par `spl_object_id` de l'article d'origine
     */
    public function __construct(private array $choix)
    {
    }

    public function retenir(MaterielLisible $article): ResultatOffre
    {
        $prix = $this->choix[spl_object_id($article)] ?? null;
        if (null === $prix) {
            return new ResultatOffre(null, [new Avertissement('offer_missing', ['article' => ResolveurOffre::nom($article)])]);
        }
        $retenu = $prix->getMateriau();
        $lot = $prix->unitesParLotEffectif();
        $equivalent = $retenu !== $article;

        return new ResultatOffre(
            new Offre($retenu, $prix->getFournisseur()->getId() ?? 0, $lot !== $retenu->getUnitesParLot() ? $lot : null, $equivalent ? NatureOffre::Equivalente : NatureOffre::Exacte),
            $equivalent ? [new Avertissement('offer_equivalent_used', ['original' => ResolveurOffre::nom($article), 'replacement' => ResolveurOffre::nom($retenu)])] : [],
        );
    }
}
