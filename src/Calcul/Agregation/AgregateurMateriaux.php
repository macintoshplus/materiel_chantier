<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

use App\Calcul\Optimisation\OptimiseurBesoins;
use App\Calcul\Substitution\NatureOffre;
use App\Calcul\Substitution\SubstitutionArticle;
use App\Calcul\Substitution\ResolveurOffre;
use App\Catalogue\MaterielLisible;
use App\Geometrie\Avertissement;

/**
 * Agrège les besoins bruts d'un projet : substitution éventuelle de l'article (fournisseur, équivalent), regroupement
 * par article retenu, optimisation des coupes sur les pièces réunies, marge de sécurité puis lots entiers.
 *
 * Ordre : besoins bruts, article retenu, optimisation (avec les dimensions et le lot de l'article retenu), marge, lots.
 */
final readonly class AgregateurMateriaux
{
    public function __construct(
        private ResolveurMarge $marges,
        private OptimiseurBesoins $optimiseur = new OptimiseurBesoins(),
    ) {
    }

    public function calculer(BesoinsProjet $besoins, ?SubstitutionArticle $substitution = null): ResultatAgregation
    {
        $avertissements = [];
        /** @var array<string, array{besoin: BesoinArticle, base: MaterielLisible, initial: ?MaterielLisible, nature: NatureOffre, manquant: bool}> $groupes */
        $groupes = [];

        foreach ($besoins->articles() as $besoin) {
            $retenu = $besoin->article;
            $base = $besoin->article;
            $unitesLot = null;
            $initial = null;
            $nature = NatureOffre::Exacte;
            $manquant = false;
            if (null !== $substitution) {
                $resultat = $substitution->retenir($besoin->article);
                foreach ($resultat->avertissements as $a) {
                    $avertissements[] = $a;
                }
                if (null === $resultat->offre) {
                    $manquant = true;
                } else {
                    $nature = $resultat->offre->nature;
                    $base = $resultat->offre->article;
                    $unitesLot = $resultat->offre->unitesParLot;
                    $retenu = null !== $unitesLot ? new ArticleOffert($base, $unitesLot) : $base;
                    if ($resultat->offre->article !== $besoin->article) {
                        $initial = $besoin->article;
                    }
                }
            }
            $cle = spl_object_id($base).'|'.($unitesLot ?? 0).'|'.$besoin->type->value.'|'.($manquant ? 'm' : 'o');
            if (isset($groupes[$cle])) {
                $groupes[$cle]['besoin'] = $groupes[$cle]['besoin']->fusionner(new BesoinArticle($retenu, $besoin->type, $besoin->quantite, $besoin->pieces, $besoin->categorie, $besoin->cles, $besoin->origines));
            } else {
                $groupes[$cle] = [
                    'base' => $base,
                    'besoin' => new BesoinArticle($retenu, $besoin->type, $besoin->quantite, $besoin->pieces, $besoin->categorie, $besoin->cles, $besoin->origines),
                    'initial' => $initial,
                    'nature' => $nature,
                    'manquant' => $manquant,
                ];
            }
        }

        $achats = [];
        foreach ($groupes as $groupe) {
            $besoin = $groupe['besoin'];
            try {
                $optimise = $this->optimiser($besoin);
            } catch (\InvalidArgumentException) {
                $avertissements[] = new Avertissement('article_dimension_missing', ['article' => ResolveurOffre::nom($besoin->article)]);

                continue;
            }
            $marge = $this->marges->resoudre($besoin->article);
            $parLot = max(1, \is_int($besoin->article->valeur('unites_par_lot')) ? $besoin->article->valeur('unites_par_lot') : 1);
            $lots = Marge::lots($optimise['unites'], $marge->centiemes, $parLot);
            $achetees = $lots * $parLot;
            $achats[] = new LigneAchat(
                $groupe['base'],
                $groupe['initial'],
                $besoin->type,
                $besoin->cles,
                $besoin->origines,
                $besoin->quantite,
                $optimise['unites'],
                $optimise['chute'],
                $marge->centiemes,
                $marge->source,
                Marge::quantiteMilli($optimise['unites'], $marge->centiemes),
                $parLot,
                $lots,
                $achetees,
                $achetees - $optimise['unites'],
                $groupe['nature'],
                $groupe['manquant'],
            );
        }

        return new ResultatAgregation($achats, $besoins->sansArticle(), $avertissements);
    }

    /**
     * @return array{unites: int, chute: int}
     */
    private function optimiser(BesoinArticle $besoin): array
    {
        $optimise = $this->optimiseur->optimiser($besoin->versLigne(), $besoin->article);

        return ['unites' => $optimise->unitesAchetees, 'chute' => $optimise->chute];
    }
}
