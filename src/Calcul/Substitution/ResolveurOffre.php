<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

use App\Catalogue\MaterielLisible;
use App\Geometrie\Avertissement;

/**
 * Chaîne de résolution d'un article chez un fournisseur : article épinglé, équivalent chez ce fournisseur, épinglé
 * ou équivalent chez un autre fournisseur, puis « prix manquant ». Le premier maillon qui répond l'emporte :
 * un équivalent n'est jamais cherché quand la correspondance exacte existe.
 */
final readonly class ResolveurOffre
{
    /**
     * @param list<MaillonOffre> $maillons
     */
    public function __construct(private array $maillons)
    {
    }

    public static function standard(SourceOffres $offres, \App\Calcul\Materiau\SourceCandidats $candidats): self
    {
        $exacte = new OffreExacte($offres);
        $equivalente = new OffreEquivalente($candidats, $offres);

        return new self([$exacte, $equivalente, new OffreAutreFournisseur([$exacte, $equivalente])]);
    }

    public function resoudre(DemandeOffre $demande): ResultatOffre
    {
        foreach ($this->maillons as $maillon) {
            $offre = $maillon->resoudre($demande);
            if (null === $offre) {
                continue;
            }

            return new ResultatOffre($offre, match ($offre->nature) {
                NatureOffre::Exacte => [],
                NatureOffre::Equivalente => [new Avertissement('offer_equivalent_used', ['original' => self::nom($demande->epingle), 'replacement' => self::nom($offre->article)])],
                NatureOffre::ExacteAutreFournisseur, NatureOffre::EquivalenteAutreFournisseur => [new Avertissement('offer_other_supplier_used', ['article' => self::nom($offre->article)])],
            });
        }

        return new ResultatOffre(null, [new Avertissement('offer_missing', ['article' => self::nom($demande->epingle)])]);
    }

    public static function nom(MaterielLisible $article): string
    {
        $reference = $article->valeur('reference');

        return \is_string($reference) && '' !== $reference ? $reference : $article->categorie();
    }
}
