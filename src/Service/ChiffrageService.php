<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Agregation\AgregateurMateriaux;
use App\Calcul\Agregation\BesoinArticle;
use App\Calcul\Agregation\BesoinsProjet;
use App\Calcul\Agregation\ConfigurationMarges;
use App\Calcul\Agregation\LigneAchat;
use App\Calcul\Agregation\ResolveurMarge;
use App\Calcul\Materiau\SourceCandidats;
use App\Calcul\Substitution\SubstitutionArticle;
use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\RegistreCategories;
use App\Chiffrage\Besoin;
use App\Chiffrage\Contexte;
use App\Chiffrage\Montant;
use App\Chiffrage\QuantiteBrute;
use App\Chiffrage\Repartition;
use App\Chiffrage\Strategie\FournisseurUnique;
use App\Chiffrage\Strategie\Manuelle;
use App\Chiffrage\Strategie\MeilleurPrix;
use App\Chiffrage\Strategie\PlusProche;
use App\Chiffrage\StrategieFournisseur;
use App\Chiffrage\SubstitutionChoisie;
use App\Chiffrage\SubstitutionForcee;
use App\Entity\Chiffrage;
use App\Entity\Fournisseur;
use App\Entity\LigneChiffrage;
use App\Entity\Materiau;
use App\Entity\PrixMateriau;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Enum\TypeLigneChiffrage;
use App\Enum\TypeStrategie;
use App\Geometrie\Avertissement;
use App\Repository\FournisseurRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée un chiffrage : besoins du projet (variante active de chaque support), articles choisis par la stratégie de
 * fournisseur, optimisation des coupes et lots avec l'article retenu (moteur d'agrégation), puis enregistrement d'un
 * instantané (lignes agrégées, parts par pièce et par surface, livraison). Le chiffrage ne dépend plus du catalogue.
 */
final class ChiffrageService
{
    public function __construct(
        private readonly CalculProjet $calcul,
        private readonly RegistreCategories $categories,
        private readonly SourceCandidats $candidats,
        private readonly PrixService $prix,
        private readonly FournisseurRepository $fournisseurs,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Articles (références) que les besoins du projet demandent, pour proposer un fournisseur par article.
     *
     * @return list<Materiau>
     */
    public function articlesDuProjet(Projet $projet): array
    {
        $global = new BesoinsProjet();
        foreach ($this->calcul->preparer($projet)->supports as $support) {
            $global->reunir($support->besoins);
        }
        $articles = [];
        foreach ($global->articles() as $besoin) {
            if ($besoin->article instanceof Materiau) {
                $articles[$besoin->article->getReference()] = $besoin->article;
            }
        }
        ksort($articles);

        return array_values($articles);
    }

    /**
     * @param array<string, mixed> $parametres `fournisseur` (id imposé), `adresse` (`texte`, `latitude`, `longitude` ; défaut : le chantier du
     *                                         projet), `choix` (référence d'article → id de fournisseur, stratégie manuelle), `marges` (`materiaux`, `categories`)
     */
    public function creer(Projet $projet, TypeStrategie $type, \DateTimeImmutable $date, array $parametres = [], string $libelle = ''): Chiffrage
    {
        $preparation = $this->calcul->preparer($projet);
        $global = new BesoinsProjet();
        foreach ($preparation->supports as $support) {
            $global->reunir($support->besoins);
        }
        $parametres = $this->parametres($projet, $parametres);
        $impose = null !== $parametres['fournisseur'] ? $this->fournisseurs->find($parametres['fournisseur']) : null;
        $choixManuels = [];
        foreach ($parametres['choix'] as $reference => $idFournisseur) {
            $fournisseur = $this->fournisseurs->find($idFournisseur);
            if (null !== $fournisseur) {
                $choixManuels[(string) $reference] = $fournisseur;
            }
        }

        $resolveurMarge = new ResolveurMarge($this->categories, ConfigurationMarges::depuis($projet->getParametres(), $parametres['marges']));
        $agregateur = new AgregateurMateriaux($resolveurMarge);
        $specifications = $preparation->specifications;
        $source = new SourcePrixCatalogue(
            $this->prix,
            $this->em,
            $this->candidats,
            $this->fournisseurs->findBy(['actif' => true], ['nom' => 'ASC', 'id' => 'ASC']),
            static fn (MaterielLisible $a): ?MaterielSpecification => $specifications[spl_object_id($a)] ?? null,
        );
        $adresse = $parametres['adresse'];
        $contexte = new Contexte(
            $impose,
            null !== $adresse && is_numeric($adresse['latitude'] ?? null) ? (float) $adresse['latitude'] : null,
            null !== $adresse && is_numeric($adresse['longitude'] ?? null) ? (float) $adresse['longitude'] : null,
            static fn (MaterielLisible $a): int => $resolveurMarge->resoudre($a)->centiemes,
            $choixManuels,
        );

        // Besoins regroupés par article d'origine, quantité brute (avant marge et lots) de chacun.
        /** @var array<int, list<BesoinArticle>> $parArticle */
        $parArticle = [];
        foreach ($global->articles() as $besoin) {
            $parArticle[spl_object_id($besoin->article)][] = $besoin;
        }
        $besoinsChoix = [];
        $idsChoix = [];
        foreach ($parArticle as $id => $besoins) {
            $article = $besoins[0]->article;
            if (!$article instanceof Materiau) {
                continue;
            }
            $unites = fn (?SubstitutionArticle $substitution): int => array_sum(array_map(
                static fn (LigneAchat $a): int => $a->unitesBesoin,
                $agregateur->calculer(self::cumul($besoins), $substitution)->achats,
            ));
            $besoinsChoix[] = new Besoin($article, new QuantiteBrute($article, $unites(null), static fn (Materiau $c): int => $unites(new SubstitutionForcee($c))));
            $idsChoix[] = $id;
        }
        $choisis = $this->strategie($type, $source)->choisirEnsemble($besoinsChoix, $date, $contexte);
        /** @var array<int, PrixMateriau|null> $choix */
        $choix = array_fill_keys(array_keys($parArticle), null);
        foreach ($idsChoix as $k => $id) {
            $choix[$id] = $choisis[$k];
        }

        // Un passage du moteur par fournisseur retenu : un même article n'a ainsi qu'un seul prix dans un passage.
        /** @var array<string, list<BesoinArticle>> $groupes */
        $groupes = [];
        foreach ($parArticle as $id => $besoins) {
            $fournisseur = $choix[$id]?->getFournisseur();
            foreach ($besoins as $besoin) {
                $groupes[null === $fournisseur ? 'manquant' : (string) spl_object_id($fournisseur)][] = $besoin;
            }
        }

        $avertissements = [];
        foreach ($preparation->erreurs as $a) {
            $avertissements[] = $a;
        }
        foreach ($preparation->avertissements as $a) {
            $avertissements[] = $a;
        }
        $chiffrage = new Chiffrage($projet, '' === $libelle ? $projet->getNom() : $libelle, $date, $type, $parametres);
        $substitution = new SubstitutionChoisie($choix);
        $fournisseursUtilises = [];
        foreach ($groupes as $besoins) {
            $resultat = $agregateur->calculer(self::cumul($besoins), $substitution);
            foreach ($resultat->avertissements as $a) {
                $avertissements[] = $a;
            }
            foreach ($this->lignes($chiffrage, $besoins, $resultat->achats, $choix, $preparation->supports) as $ligne) {
                $chiffrage->ajouterLigne($ligne);
                if (TypeLigneChiffrage::Article === $ligne->getType() && null !== $ligne->getFournisseur()) {
                    $fournisseursUtilises[spl_object_id($ligne->getFournisseur())] = $ligne->getFournisseur();
                }
            }
        }
        foreach ($fournisseursUtilises as $fournisseur) {
            $frais = Montant::centimes($fournisseur->getFraisLivraisonTtc());
            if ($frais > 0) {
                $chiffrage->ajouterLigne(new LigneChiffrage(
                    $chiffrage,
                    TypeLigneChiffrage::Livraison,
                    fournisseur: $fournisseur,
                    fournisseurNom: $fournisseur->getNom(),
                    fournisseurAdresse: self::adresse($fournisseur),
                    nombreLots: 1,
                    quantiteAchetee: 1,
                    quantiteBesoin: 1,
                    prixLotCentimes: $frais,
                    totalCentimes: $frais,
                ));
            }
        }

        $chiffrage->definirAvertissements(self::dedoublonner($avertissements));
        $this->em->persist($chiffrage);
        $this->em->flush();

        return $chiffrage;
    }

    private function strategie(TypeStrategie $type, SourcePrixCatalogue $source): StrategieFournisseur
    {
        return match ($type) {
            TypeStrategie::FournisseurUnique => new FournisseurUnique($source),
            TypeStrategie::MeilleurPrix => new MeilleurPrix($source),
            TypeStrategie::PlusProche => new PlusProche($source),
            TypeStrategie::Manuelle => new Manuelle($source),
        };
    }

    /**
     * @param list<BesoinArticle> $besoins
     */
    private static function cumul(array $besoins): BesoinsProjet
    {
        $cumul = new BesoinsProjet();
        foreach ($besoins as $besoin) {
            $cumul->ajouterBesoin($besoin);
        }

        return $cumul;
    }

    /**
     * Lignes d'un passage : un achat agrégé par article, puis sa répartition entre les supports (pièce, surface) au
     * prorata du besoin brut de chaque support.
     *
     * @param list<BesoinArticle>           $originaux
     * @param list<LigneAchat>              $achats
     * @param array<int, PrixMateriau|null> $choix
     * @param list<BesoinsSupport>          $supports
     *
     * @return list<LigneChiffrage>
     */
    private function lignes(Chiffrage $chiffrage, array $originaux, array $achats, array $choix, array $supports): array
    {
        $lignes = [];
        foreach ($achats as $achat) {
            $prix = null;
            $articlesOrigine = [];
            foreach ($originaux as $original) {
                $retenu = ($choix[spl_object_id($original->article)] ?? null)?->getMateriau() ?? $original->article;
                if ($retenu === $achat->article && $original->type === $achat->type) {
                    $articlesOrigine[] = $original->article;
                    $prix ??= $choix[spl_object_id($original->article)] ?? null;
                }
            }
            $fournisseur = $achat->prixManquant ? null : $prix?->getFournisseur();
            $prixLot = null === $prix || $achat->prixManquant ? null : Montant::centimes($prix->getPrixLotTtc());
            $total = null === $prixLot ? 0 : $achat->lots * $prixLot;
            $taux = $achat->quantiteAchetee > 0 ? intdiv($achat->chute * 10000 + intdiv($achat->quantiteAchetee, 2), $achat->quantiteAchetee) : 0;
            $reference = $achat->article->valeur('reference');
            $initiale = $achat->articleInitial?->valeur('reference');
            $commun = [
                'categorie' => $achat->article->categorie(),
                'materiauId' => $achat->article instanceof Materiau ? $achat->article->getId() : null,
                'materiauReference' => \is_string($reference) ? $reference : null,
                'materiauLibelle' => self::texte($achat->article->valeur('libelle')),
                'longueur' => self::entier($achat->article->valeur('longueur')),
                'largeur' => self::entier($achat->article->valeur('largeur')),
                'epaisseur' => self::entier($achat->article->valeur('epaisseur')),
                'hauteur' => self::entier($achat->article->valeur('hauteur')),
                'referenceInitiale' => \is_string($initiale) ? $initiale : null,
                'fournisseur' => $fournisseur,
                'fournisseurNom' => $fournisseur?->getNom(),
                'fournisseurAdresse' => null === $fournisseur ? null : self::adresse($fournisseur),
                'referenceFournisseur' => $prix?->getReferenceFournisseur(),
                'margeCentiemes' => $achat->margeCentiemes,
                'unitesParLot' => $achat->unitesParLot,
                'prixLotCentimes' => $prixLot,
                'prixMateriauId' => $prix?->getId(),
                'prixManquant' => $achat->prixManquant,
            ];
            $lignes[] = new LigneChiffrage(
                $chiffrage,
                TypeLigneChiffrage::Article,
                ...$commun,
                quantiteBesoin: $achat->unitesBesoin,
                quantiteAvecMargeMilli: $achat->quantiteAvecMargeMilli,
                nombreLots: $achat->lots,
                quantiteAchetee: $achat->quantiteAchetee,
                totalCentimes: $total,
                chute: $achat->chute,
                tauxPerteCentiemes: $taux,
            );

            // Parts des supports : poids = besoin brut des articles d'origine de cet achat.
            $poids = [];
            foreach ($supports as $k => $support) {
                $p = 0;
                foreach ($support->besoins->articles() as $besoin) {
                    if ($besoin->type === $achat->type && \in_array($besoin->article, $articlesOrigine, true)) {
                        $p += $besoin->quantite;
                    }
                }
                if ($p > 0) {
                    $poids[$k] = $p;
                }
            }
            if ([] === $poids) {
                continue;
            }
            $cles = array_keys($poids);
            $valeurs = array_values($poids);
            $besoins = Repartition::proportionnelle($achat->unitesBesoin, $valeurs);
            $milli = Repartition::proportionnelle($achat->quantiteAvecMargeMilli, $valeurs);
            $achetees = Repartition::proportionnelle($achat->quantiteAchetee, $valeurs);
            $totaux = Repartition::proportionnelle($total, $valeurs);
            $chutes = Repartition::proportionnelle($achat->chute, $valeurs);
            foreach ($cles as $i => $k) {
                $support = $supports[$k]->support;
                $lignes[] = new LigneChiffrage(
                    $chiffrage,
                    TypeLigneChiffrage::Repartition,
                    ...$commun,
                    piece: $supports[$k]->piece,
                    surface: $support instanceof Surface ? $support : null,
                    pieceNom: $supports[$k]->piece->getNom(),
                    surfaceNom: $support->getNom(),
                    quantiteBesoin: $besoins[$i],
                    quantiteAvecMargeMilli: $milli[$i],
                    nombreLots: 0,
                    quantiteAchetee: $achetees[$i],
                    totalCentimes: $totaux[$i],
                    chute: $chutes[$i],
                    tauxPerteCentiemes: $taux,
                );
            }
        }

        return $lignes;
    }

    /**
     * @param array<string, mixed> $saisie
     *
     * @return array{fournisseur: ?int, adresse: ?array<string, mixed>, choix: array<string, int>, marges: array<string, mixed>}
     */
    private function parametres(Projet $projet, array $saisie): array
    {
        $adresse = \is_array($saisie['adresse'] ?? null) ? $saisie['adresse'] : null;
        if (null === $adresse && (null !== $projet->getAdresseChantier() || null !== $projet->getLatitude())) {
            $adresse = ['texte' => $projet->getAdresseChantier(), 'latitude' => $projet->getLatitude(), 'longitude' => $projet->getLongitude()];
        }
        $choix = [];
        if (\is_array($saisie['choix'] ?? null)) {
            foreach ($saisie['choix'] as $reference => $id) {
                if (is_numeric($id) && (int) $id > 0) {
                    $choix[(string) $reference] = (int) $id;
                }
            }
        }

        return [
            'fournisseur' => is_numeric($saisie['fournisseur'] ?? null) && (int) $saisie['fournisseur'] > 0 ? (int) $saisie['fournisseur'] : null,
            'adresse' => $adresse,
            'choix' => $choix,
            'marges' => \is_array($saisie['marges'] ?? null) ? $saisie['marges'] : [],
        ];
    }

    /**
     * @param list<Avertissement> $avertissements
     *
     * @return list<array{code: string, params: array<string, scalar>}>
     */
    private static function dedoublonner(array $avertissements): array
    {
        $uniques = [];
        foreach ($avertissements as $a) {
            $params = [];
            foreach ($a->params as $nom => $valeur) {
                $params[(string) $nom] = \is_scalar($valeur) ? $valeur : (string) json_encode($valeur);
            }
            $uniques[json_encode([$a->code, $params], \JSON_THROW_ON_ERROR)] = ['code' => $a->code, 'params' => $params];
        }

        return array_values($uniques);
    }

    public static function adresse(Fournisseur $fournisseur): ?string
    {
        $lignes = array_filter([
            $fournisseur->getAdresseLigne1(),
            $fournisseur->getAdresseLigne2(),
            trim(($fournisseur->getCodePostal() ?? '').' '.($fournisseur->getVille() ?? '')),
            $fournisseur->getPays(),
        ], static fn (?string $l): bool => null !== $l && '' !== $l);

        return [] === $lignes ? null : implode("\n", $lignes);
    }

    private static function texte(mixed $valeur): ?string
    {
        return \is_string($valeur) ? $valeur : null;
    }

    private static function entier(mixed $valeur): ?int
    {
        return \is_int($valeur) ? $valeur : null;
    }
}
