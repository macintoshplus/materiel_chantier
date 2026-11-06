<?php

declare(strict_types=1);

namespace App\Tests\Chiffrage\Strategie;

use App\Chiffrage\Contexte;
use App\Chiffrage\Strategie\FournisseurUnique;
use App\Chiffrage\Strategie\Manuelle;
use App\Chiffrage\Strategie\MeilleurPrix;
use App\Chiffrage\Strategie\PlusProche;
use App\Tests\Chiffrage\Support\CatalogueMemoire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StrategiesTest extends TestCase
{
    private \DateTimeImmutable $date;

    protected function setUp(): void
    {
        $this->date = new \DateTimeImmutable('2024-06-01');
    }

    /**
     * Lot de 5 à 12 € (A) contre lot de 1 à 3 € (B) : 5 unités -> A (12 contre 15), 2 unités -> B (6 contre 12).
     */
    #[DataProvider('lotsContreUnites')]
    public function test_meilleur_prix_compare_le_cout_total_et_non_le_prix_unitaire(int $unites, string $attendu): void
    {
        $c = new CatalogueMemoire();
        $a = $c->fournisseur('A');
        $b = $c->fournisseur('B');
        $article = CatalogueMemoire::article('X');
        $c->vend($a, $article, '12.00', 5);
        $c->vend($b, $article, '3.00', 1);

        $prix = (new MeilleurPrix($c))->choisir($article, CatalogueMemoire::besoin($article, $unites)->quantite, $this->date, new Contexte());

        self::assertSame($attendu, $prix?->getFournisseur()->getNom());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function lotsContreUnites(): iterable
    {
        yield '5 unités' => [5, 'A'];
        yield '2 unités' => [2, 'B'];
    }

    public function test_meilleur_prix_tient_compte_de_la_livraison_et_prefere_un_seul_fournisseur(): void
    {
        $c = new CatalogueMemoire();
        $a = $c->fournisseur('A');
        $b = $c->fournisseur('B', '20.00');
        $x = CatalogueMemoire::article('X');
        $y = CatalogueMemoire::article('Y');
        $c->vend($a, $x, '100.00');
        $c->vend($a, $y, '15.00');
        $c->vend($b, $x, '90.00');
        $c->vend($b, $y, '10.00');

        $choix = (new MeilleurPrix($c))->choisirEnsemble([CatalogueMemoire::besoin($x, 1), CatalogueMemoire::besoin($y, 1)], $this->date, new Contexte());

        // A seul : 115 ; B seul : 100 + 20 de livraison = 120 ; la livraison de B ne se rembourse pas.
        self::assertSame(['A', 'A'], array_map(static fn ($p) => $p?->getFournisseur()->getNom(), $choix));
    }

    public function test_meilleur_prix_panache_les_fournisseurs_quand_cela_rembourse_la_livraison(): void
    {
        $c = new CatalogueMemoire();
        $a = $c->fournisseur('A');
        $b = $c->fournisseur('B', '5.00');
        $x = CatalogueMemoire::article('X');
        $y = CatalogueMemoire::article('Y');
        $c->vend($a, $x, '100.00');
        $c->vend($a, $y, '50.00');
        $c->vend($b, $x, '60.00');

        $choix = (new MeilleurPrix($c))->choisirEnsemble([CatalogueMemoire::besoin($x, 1), CatalogueMemoire::besoin($y, 1)], $this->date, new Contexte());

        self::assertSame(['B', 'A'], array_map(static fn ($p) => $p?->getFournisseur()->getNom(), $choix));
    }

    public function test_meilleur_prix_glouton_au_dela_de_la_limite_exhaustive(): void
    {
        $c = new CatalogueMemoire();
        $x = CatalogueMemoire::article('X');
        for ($i = 1; $i <= MeilleurPrix::LIMITE_EXHAUSTIVE + 2; ++$i) {
            $c->vend($c->fournisseur('F'.$i), $x, (string) (50 - $i).'.00');
        }

        $choix = (new MeilleurPrix($c))->choisirEnsemble([CatalogueMemoire::besoin($x, 1)], $this->date, new Contexte());

        self::assertSame('F10', $choix[0]?->getFournisseur()->getNom());
    }

    public function test_meilleur_prix_article_sans_prix_donne_null(): void
    {
        $c = new CatalogueMemoire();
        $c->fournisseur('A');
        $x = CatalogueMemoire::article('X');

        self::assertSame([null], (new MeilleurPrix($c))->choisirEnsemble([CatalogueMemoire::besoin($x, 1)], $this->date, new Contexte()));
    }

    public function test_plus_proche_classe_par_distance_puis_place_les_sans_position_en_dernier(): void
    {
        $c = new CatalogueMemoire();
        $c->fournisseur('Zéro');
        $c->fournisseur('Lyon', null, '45.76', '4.84');
        $c->fournisseur('Orléans', null, '47.90', '1.91');
        $c->fournisseur('Lille', null, '50.63', '3.06');
        $contexte = new Contexte(latitude: 48.85, longitude: 2.35);

        $noms = array_map(static fn ($f): string => $f->getNom(), (new PlusProche($c))->classement($contexte));

        self::assertSame(['Orléans', 'Lille', 'Lyon', 'Zéro'], $noms);
    }

    public function test_plus_proche_se_replie_sur_le_suivant_qui_vend_l_article(): void
    {
        $c = new CatalogueMemoire();
        $proche = $c->fournisseur('Proche', null, '47.90', '1.91');
        $loin = $c->fournisseur('Loin', null, '45.76', '4.84');
        $x = CatalogueMemoire::article('X');
        $y = CatalogueMemoire::article('Y');
        $c->vend($proche, $x, '10.00');
        $c->vend($loin, $x, '1.00');
        $c->vend($loin, $y, '1.00');
        $contexte = new Contexte(latitude: 48.85, longitude: 2.35);

        $choix = (new PlusProche($c))->choisirEnsemble([CatalogueMemoire::besoin($x, 1), CatalogueMemoire::besoin($y, 1)], $this->date, $contexte);

        self::assertSame(['Proche', 'Loin'], array_map(static fn ($p) => $p?->getFournisseur()->getNom(), $choix));
    }

    public function test_plus_proche_sans_position_est_alphabetique(): void
    {
        $c = new CatalogueMemoire();
        $c->fournisseur('B', null, '47.90', '1.91');
        $c->fournisseur('A', null, '45.76', '4.84');

        $noms = array_map(static fn ($f): string => $f->getNom(), (new PlusProche($c))->classement(new Contexte()));

        self::assertSame(['A', 'B'], $noms);
    }

    public function test_fournisseur_unique_n_a_pas_de_repli(): void
    {
        $c = new CatalogueMemoire();
        $a = $c->fournisseur('A');
        $b = $c->fournisseur('B');
        $x = CatalogueMemoire::article('X');
        $y = CatalogueMemoire::article('Y');
        $c->vend($a, $x, '10.00');
        $c->vend($b, $x, '1.00');
        $c->vend($b, $y, '1.00');
        $besoins = [CatalogueMemoire::besoin($x, 1), CatalogueMemoire::besoin($y, 1)];

        $choix = (new FournisseurUnique($c))->choisirEnsemble($besoins, $this->date, new Contexte($a));

        self::assertSame('A', $choix[0]?->getFournisseur()->getNom());
        self::assertNull($choix[1], 'Y n\'est pas vendu par A : pas de repli sur B');
        self::assertSame([null, null], (new FournisseurUnique($c))->choisirEnsemble($besoins, $this->date, new Contexte()));
    }

    public function test_manuelle_suit_le_choix_par_reference(): void
    {
        $c = new CatalogueMemoire();
        $a = $c->fournisseur('A');
        $b = $c->fournisseur('B');
        $x = CatalogueMemoire::article('X');
        $y = CatalogueMemoire::article('Y');
        $c->vend($a, $x, '1.00');
        $c->vend($b, $x, '9.00');
        $c->vend($a, $y, '1.00');
        $besoins = [CatalogueMemoire::besoin($x, 1), CatalogueMemoire::besoin($y, 1)];

        $choix = (new Manuelle($c))->choisirEnsemble($besoins, $this->date, new Contexte(choix: ['X' => $b]));

        self::assertSame('B', $choix[0]?->getFournisseur()->getNom());
        self::assertNull($choix[1], 'aucun choix pour Y : prix manquant');
    }
}
