<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Agregation;

use App\Calcul\Agregation\AgregateurMateriaux;
use App\Calcul\Agregation\BesoinsProjet;
use App\Calcul\Agregation\ConfigurationMarges;
use App\Calcul\Agregation\LigneAchat;
use App\Calcul\Agregation\Marge;
use App\Calcul\Agregation\ResolveurMarge;
use App\Calcul\Agregation\SourceMarge;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Substitution\Offre;
use App\Calcul\Substitution\ResultatOffre;
use App\Calcul\Substitution\SubstitutionArticle;
use App\Catalogue\Categorie\PlaquePlatre;
use App\Catalogue\Categorie\ProfilOssature;
use App\Catalogue\MaterielLisible;
use App\Catalogue\RegistreCategories;
use App\Tests\Calcul\Support\ArticleDouble;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AgregateurMateriauxTest extends TestCase
{
    private function registre(): RegistreCategories
    {
        return new RegistreCategories([new PlaquePlatre(), new ProfilOssature()]);
    }

    private function plaque(int $parLot = 1, ?string $marge = null): ArticleDouble
    {
        return new ArticleDouble('plaque_platre', ['reference' => 'BA13', 'longueur' => 2500, 'largeur' => 1200, 'unites_par_lot' => $parLot, 'marge_securite_pct' => $marge]);
    }

    private function rail(string $reference, int $longueur): ArticleDouble
    {
        return new ArticleDouble('profil_ossature', ['reference' => $reference, 'longueur' => $longueur, 'unites_par_lot' => 1]);
    }

    private function calculer(BesoinsProjet $besoins, ConfigurationMarges $configuration = new ConfigurationMarges(), ?SubstitutionArticle $substitution = null): LigneAchat
    {
        $resultat = (new AgregateurMateriaux(new ResolveurMarge($this->registre(), $configuration)))->calculer($besoins, $substitution);
        self::assertCount(1, $resultat->achats);

        return $resultat->achats[0];
    }

    /**
     * @return iterable<string, array{int, int, int, int}>
     */
    public static function casLots(): iterable
    {
        yield '11 plaques, 1 par lot : ceil(12,1) = 13' => [1, 13, 13, 12100];
        yield '11 plaques, 5 par lot : 3 lots, 15 plaques' => [5, 3, 15, 12100];
    }

    #[DataProvider('casLots')]
    public function test_marge_de_dix_pour_cent_arrondie_au_lot(int $parLot, int $lots, int $achetees, int $milli): void
    {
        $plaque = $this->plaque($parLot);
        $besoins = new BesoinsProjet();
        $besoins->ajouter([LigneBesoin::nombre('plaques', 'x', 'plaque_platre', 11, [], $plaque)], 'piece');

        $achat = $this->calculer($besoins);

        self::assertSame(1000, $achat->margeCentiemes);
        self::assertSame(SourceMarge::Categorie, $achat->sourceMarge);
        self::assertSame(11, $achat->unitesBesoin);
        self::assertSame($lots, $achat->lots);
        self::assertSame($achetees, $achat->quantiteAchetee);
        self::assertSame($milli, $achat->quantiteAvecMargeMilli);
        self::assertSame($achetees - 11, $achat->chute);
    }

    /**
     * @return iterable<string, array{ConfigurationMarges, int, SourceMarge}>
     */
    public static function casPriorite(): iterable
    {
        yield 'defaut global' => [new ConfigurationMarges(defautGlobal: 300), 300, SourceMarge::Globale];
        yield 'categorie' => [new ConfigurationMarges(), 1000, SourceMarge::Categorie];
        yield 'projet categorie' => [new ConfigurationMarges(projetCategories: ['plaque_platre' => 1500]), 1500, SourceMarge::Projet];
        yield 'projet materiau avant projet categorie' => [new ConfigurationMarges(projetMateriaux: ['BA13' => 2000], projetCategories: ['plaque_platre' => 1500]), 2000, SourceMarge::Projet];
        yield 'chiffrage avant projet' => [new ConfigurationMarges(chiffrageCategories: ['plaque_platre' => 500], projetMateriaux: ['BA13' => 2000]), 500, SourceMarge::Chiffrage];
        yield 'chiffrage materiau avant chiffrage categorie' => [new ConfigurationMarges(chiffrageMateriaux: ['BA13' => 700], chiffrageCategories: ['plaque_platre' => 500]), 700, SourceMarge::Chiffrage];
    }

    #[DataProvider('casPriorite')]
    public function test_priorite_des_marges(ConfigurationMarges $configuration, int $attendu, SourceMarge $source): void
    {
        $retenue = (new ResolveurMarge($this->registre(), $configuration))->resoudre($this->plaque());
        if (SourceMarge::Globale === $source) {
            // Catégorie inconnue du registre : seul le défaut global reste.
            $retenue = (new ResolveurMarge(new RegistreCategories([]), $configuration))->resoudre($this->plaque());
        }

        self::assertSame($attendu, $retenue->centiemes);
        self::assertSame($source, $retenue->source);
    }

    public function test_marge_propre_au_materiau_avant_la_categorie_apres_le_projet(): void
    {
        $plaque = $this->plaque(1, '12.50');

        $propre = (new ResolveurMarge($this->registre()))->resoudre($plaque);
        $projet = (new ResolveurMarge($this->registre(), new ConfigurationMarges(projetCategories: ['plaque_platre' => 100])))->resoudre($plaque);

        self::assertSame([1250, SourceMarge::Materiau], [$propre->centiemes, $propre->source]);
        self::assertSame([100, SourceMarge::Projet], [$projet->centiemes, $projet->source]);
    }

    #[DataProvider('valeursMarge')]
    public function test_centiemes(mixed $valeur, ?int $attendu): void
    {
        self::assertSame($attendu, Marge::centiemes($valeur));
    }

    /**
     * @return iterable<string, array{mixed, ?int}>
     */
    public static function valeursMarge(): iterable
    {
        yield 'entier' => [10, 1000];
        yield 'texte decimal' => ['12,5', 1250];
        yield 'decimal bd' => ['10.00', 1000];
        yield 'trop grand' => ['101', null];
        yield 'negatif' => ['-1', null];
        yield 'vide' => ['', null];
        yield 'null' => [null, null];
    }

    public function test_deux_pieces_agregees_en_lots_entiers_avec_chute(): void
    {
        $plaque = $this->plaque(5);
        $besoins = new BesoinsProjet();
        $besoins->ajouter([LigneBesoin::nombre('p1', 'x', 'plaque_platre', 6, [], $plaque)], 'chambre');
        $besoins->ajouter([LigneBesoin::nombre('p1', 'y', 'plaque_platre', 5, [], $plaque)], 'salon');

        $achat = $this->calculer($besoins);

        self::assertSame(11, $achat->besoin);
        self::assertSame(['chambre', 'salon'], $achat->origines);
        self::assertSame(3, $achat->lots);
        self::assertSame(15, $achat->quantiteAchetee);
        self::assertSame(4, $achat->chute);
    }

    public function test_pieces_de_plusieurs_surfaces_partagent_les_barres(): void
    {
        $rail = $this->rail('R3', 3000);
        $besoins = new BesoinsProjet();
        // 2 x 1500 dans une pièce, 1 x 1500 dans l'autre : 2 barres (au lieu de 2 + 1 = 3 isolément).
        $besoins->ajouter([LigneBesoin::pieces('r', 'x', 'profil_ossature', [1500, 1500], null, [], $rail)], 'a');
        $besoins->ajouter([LigneBesoin::pieces('r', 'y', 'profil_ossature', [1500], null, [], $rail)], 'b');

        $achat = $this->calculer($besoins, new ConfigurationMarges(projetCategories: ['profil_ossature' => 0]));

        self::assertSame(2, $achat->unitesBesoin);
        self::assertSame(1500, $achat->chuteOptimisation);
        self::assertSame(2, $achat->lots);
    }

    public function test_equivalent_de_longueur_differente_recalcule_les_barres_et_la_chute(): void
    {
        $rail3 = $this->rail('R3', 3000);
        $rail26 = $this->rail('R26', 2600);
        $pieces = [1300, 1300, 1300, 1300];
        $besoins = static function () use ($rail3, $pieces): BesoinsProjet {
            $b = new BesoinsProjet();
            $b->ajouter([LigneBesoin::pieces('r', 'x', 'profil_ossature', $pieces, null, [], $rail3)], 'a');

            return $b;
        };
        $sans = ['profil_ossature' => 0];

        $direct = $this->calculer($besoins(), new ConfigurationMarges(projetCategories: $sans));
        $remplace = $this->calculer($besoins(), new ConfigurationMarges(projetCategories: $sans), new class($rail26) implements SubstitutionArticle {
            public function __construct(private readonly MaterielLisible $equivalent)
            {
            }

            public function retenir(MaterielLisible $article): ResultatOffre
            {
                return new ResultatOffre(new Offre($this->equivalent, 1));
            }
        });

        // 4 x 1,3 m : 3 m = 2 pièces par barre -> 2 barres, chute 2 x 400 ; 2,6 m = 2 pièces par barre -> 2 barres, chute 0.
        self::assertSame(2, $direct->unitesBesoin);
        self::assertSame(800, $direct->chuteOptimisation);
        self::assertSame(2, $remplace->unitesBesoin);
        self::assertSame(0, $remplace->chuteOptimisation);
        self::assertSame($rail26, $remplace->article);
        self::assertSame($rail3, $remplace->articleInitial);
    }

    public function test_equivalent_plus_court_change_le_nombre_de_barres(): void
    {
        $rail3 = $this->rail('R3', 3000);
        $rail26 = $this->rail('R26', 2600);
        $b = new BesoinsProjet();
        $b->ajouter([LigneBesoin::pieces('r', 'x', 'profil_ossature', [1400, 1400, 1400], null, [], $rail3)], 'a');
        $config = new ConfigurationMarges(projetCategories: ['profil_ossature' => 0]);

        $direct = $this->calculer($b, $config);
        $remplace = $this->calculer($b, $config, new class($rail26) implements SubstitutionArticle {
            public function __construct(private readonly MaterielLisible $equivalent)
            {
            }

            public function retenir(MaterielLisible $article): ResultatOffre
            {
                return new ResultatOffre(new Offre($this->equivalent, 1));
            }
        });

        self::assertSame(2, $direct->unitesBesoin);
        self::assertSame(1800, $direct->chuteOptimisation);
        self::assertSame(3, $remplace->unitesBesoin);
        self::assertSame(3 * 2600 - 4200, $remplace->chuteOptimisation);
    }

    public function test_besoin_sans_article_cumule_sans_lot(): void
    {
        $besoins = new BesoinsProjet();
        $besoins->ajouter([LigneBesoin::surface('colle', 'x', 'colle', 1000)], 'a');
        $besoins->ajouter([LigneBesoin::surface('colle', 'y', 'colle', 500)], 'b');

        $resultat = (new AgregateurMateriaux(new ResolveurMarge($this->registre())))->calculer($besoins);

        self::assertSame([], $resultat->achats);
        self::assertCount(1, $resultat->sansArticle);
        self::assertSame(1500, $resultat->sansArticle[0]->quantite);
    }

    public function test_offre_manquante_donne_une_ligne_prix_manquant(): void
    {
        $b = new BesoinsProjet();
        $b->ajouter([LigneBesoin::nombre('p', 'x', 'plaque_platre', 4, [], $this->plaque())], 'a');

        $achat = $this->calculer($b, new ConfigurationMarges(), new class implements SubstitutionArticle {
            public function retenir(MaterielLisible $article): ResultatOffre
            {
                return new ResultatOffre(null);
            }
        });

        self::assertTrue($achat->prixManquant);
        self::assertSame(5, $achat->lots);
    }

    public function test_offre_avec_lot_surcharge_utilise_le_lot_du_prix(): void
    {
        $plaque = $this->plaque(1);
        $b = new BesoinsProjet();
        $b->ajouter([LigneBesoin::nombre('p', 'x', 'plaque_platre', 11, [], $plaque)], 'a');

        $achat = $this->calculer($b, new ConfigurationMarges(), new class($plaque) implements SubstitutionArticle {
            public function __construct(private readonly MaterielLisible $article)
            {
            }

            public function retenir(MaterielLisible $article): ResultatOffre
            {
                return new ResultatOffre(new Offre($this->article, 1, 5));
            }
        });

        self::assertSame(5, $achat->unitesParLot);
        self::assertSame(3, $achat->lots);
        self::assertSame($plaque, $achat->article);
        self::assertNull($achat->articleInitial);
    }
}
