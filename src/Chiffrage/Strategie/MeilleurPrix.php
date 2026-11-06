<?php

declare(strict_types=1);

namespace App\Chiffrage\Strategie;

use App\Calcul\Agregation\Marge;
use App\Chiffrage\Besoin;
use App\Chiffrage\Contexte;
use App\Chiffrage\Montant;
use App\Chiffrage\QuantiteBrute;
use App\Chiffrage\SourcePrix;
use App\Chiffrage\StrategieFournisseur;
use App\Entity\Fournisseur;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;

/**
 * Minimise le coût total du projet : pour la quantité brute de chaque article, `ceil(brut × (1 + marge) / lot) ×
 * prix du lot` chez chaque fournisseur, puis choix d'un ensemble de fournisseurs (chaque article va au moins cher de
 * l'ensemble) en ajoutant le forfait de livraison de chacun. Recherche exhaustive jusqu'à 8 fournisseurs candidats,
 * glouton au-delà. Un ensemble qui laisse un article sans prix est toujours moins bon qu'un ensemble qui le couvre.
 */
final readonly class MeilleurPrix implements StrategieFournisseur
{
    public const int LIMITE_EXHAUSTIVE = 8;

    public function __construct(private SourcePrix $source)
    {
    }

    public function choisir(Materiau $materiau, QuantiteBrute $quantite, \DateTimeImmutable $date, Contexte $contexte): ?PrixMateriau
    {
        $meilleur = null;
        $coutMeilleur = 0;
        foreach ($this->source->fournisseurs() as $fournisseur) {
            $prix = $this->source->prix($materiau, $fournisseur, $date);
            if (null === $prix) {
                continue;
            }
            $cout = self::cout($prix, $quantite, $contexte);
            if (null === $meilleur || $cout < $coutMeilleur) {
                $meilleur = $prix;
                $coutMeilleur = $cout;
            }
        }

        return $meilleur;
    }

    /**
     * @param list<Besoin> $besoins
     *
     * @return list<?PrixMateriau>
     */
    public function choisirEnsemble(array $besoins, \DateTimeImmutable $date, Contexte $contexte): array
    {
        /** @var list<Fournisseur> $candidats */
        $candidats = [];
        /** @var array<int, array<int, array{PrixMateriau, int}|null>> $table [article][fournisseur] => [prix, coût en centimes] */
        $table = [];
        foreach ($this->source->fournisseurs() as $fournisseur) {
            $colonne = [];
            $vend = false;
            foreach ($besoins as $i => $besoin) {
                $prix = $this->source->prix($besoin->materiau, $fournisseur, $date);
                $colonne[$i] = null === $prix ? null : [$prix, self::cout($prix, $besoin->quantite, $contexte)];
                $vend = $vend || null !== $prix;
            }
            if ($vend) {
                $candidats[] = $fournisseur;
                foreach ($colonne as $i => $cellule) {
                    $table[$i][\count($candidats) - 1] = $cellule;
                }
            }
        }
        $forfaits = array_map(static fn (Fournisseur $f): int => Montant::centimes($f->getFraisLivraisonTtc()), $candidats);
        $membres = \count($candidats) <= self::LIMITE_EXHAUSTIVE
            ? $this->exhaustif(\count($besoins), \count($candidats), $table, $forfaits)
            : $this->glouton(\count($besoins), \count($candidats), $table, $forfaits);

        $choix = [];
        foreach (array_keys($besoins) as $i) {
            $choix[] = $this->evaluer($i, $membres, $table)[0] ?? null;
        }

        return $choix;
    }

    private static function cout(PrixMateriau $prix, QuantiteBrute $quantite, Contexte $contexte): int
    {
        $article = $prix->getMateriau();
        $lots = Marge::lots($quantite->pour($article), $contexte->margeCentiemes($article), $prix->unitesParLotEffectif());

        return $lots * Montant::centimes($prix->getPrixLotTtc());
    }

    /**
     * Le moins cher de l'ensemble pour un article (à égalité, le premier fournisseur), ou null.
     *
     * @param list<int>                                    $membres
     * @param array<int, array<int, array{PrixMateriau, int}|null>>    $table
     *
     * @return array{PrixMateriau, int}|null
     */
    private function evaluer(int $article, array $membres, array $table): ?array
    {
        $meilleur = null;
        foreach ($membres as $f) {
            $cellule = $table[$article][$f] ?? null;
            if (null !== $cellule && (null === $meilleur || $cellule[1] < $meilleur[1])) {
                $meilleur = $cellule;
            }
        }

        return $meilleur;
    }

    /**
     * Clé de comparaison d'un ensemble : articles sans prix, coût total (articles + forfaits), taille.
     *
     * @param list<int>                                 $membres
     * @param array<int, array<int, array{PrixMateriau, int}|null>> $table
     * @param list<int>                                 $forfaits
     *
     * @return array{int, int, int}
     */
    private function cle(int $nbArticles, array $membres, array $table, array $forfaits): array
    {
        $sansPrix = 0;
        $total = 0;
        for ($i = 0; $i < $nbArticles; ++$i) {
            $meilleur = $this->evaluer($i, $membres, $table);
            if (null === $meilleur) {
                ++$sansPrix;
            } else {
                $total += $meilleur[1];
            }
        }
        foreach ($membres as $f) {
            $total += $forfaits[$f];
        }

        return [$sansPrix, $total, \count($membres)];
    }

    /**
     * @param array<int, array<int, array{PrixMateriau, int}|null>> $table
     * @param list<int>                                 $forfaits
     *
     * @return list<int>
     */
    private function exhaustif(int $nbArticles, int $nbFournisseurs, array $table, array $forfaits): array
    {
        $retenu = [];
        $cleRetenue = $this->cle($nbArticles, [], $table, $forfaits);
        for ($masque = 1; $masque < (1 << $nbFournisseurs); ++$masque) {
            $membres = [];
            for ($f = 0; $f < $nbFournisseurs; ++$f) {
                if (0 !== ($masque & (1 << $f))) {
                    $membres[] = $f;
                }
            }
            $cle = $this->cle($nbArticles, $membres, $table, $forfaits);
            if ($cle < $cleRetenue) {
                $cleRetenue = $cle;
                $retenu = $membres;
            }
        }

        return $retenu;
    }

    /**
     * @param array<int, array<int, array{PrixMateriau, int}|null>> $table
     * @param list<int>                                 $forfaits
     *
     * @return list<int>
     */
    private function glouton(int $nbArticles, int $nbFournisseurs, array $table, array $forfaits): array
    {
        $membres = [];
        $cle = $this->cle($nbArticles, $membres, $table, $forfaits);
        while (true) {
            $meilleurAjout = null;
            $cleAjout = $cle;
            for ($f = 0; $f < $nbFournisseurs; ++$f) {
                if (\in_array($f, $membres, true)) {
                    continue;
                }
                $candidat = [...$membres, $f];
                $cleCandidat = $this->cle($nbArticles, $candidat, $table, $forfaits);
                if ($cleCandidat < $cleAjout) {
                    $cleAjout = $cleCandidat;
                    $meilleurAjout = $f;
                }
            }
            if (null === $meilleurAjout) {
                return $membres;
            }
            $membres[] = $meilleurAjout;
            $cle = $cleAjout;
        }
    }
}
