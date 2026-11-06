<?php

declare(strict_types=1);

namespace App\Tests\Calcul;

use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Optimisation\OptimiseurBesoins;
use App\Calcul\Optimisation\OptimiseurCoupes;
use App\Model\Catalogue\Montant;
use App\Service\MateriauxCalculator;
use App\Model\Chantier\Montant as MontantChantier;
use App\Tests\Calcul\Support\ArticleDouble;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OptimiseursTest extends TestCase
{
    /**
     * @return iterable<string, array{list<int>, int, int}>
     */
    public static function coupes(): iterable
    {
        yield 'deux pièces dans une barre' => [[2000, 1000], 3000, 1];
        yield 'pièce plus longue que la barre' => [[3920], 3000, 2];
        yield 'plusieurs pièces' => [[2500, 2500, 2500, 500], 3000, 3];
    }

    /**
     * @param list<int> $longueurs
     */
    #[DataProvider('coupes')]
    public function test_optimiseur_de_coupes(array $longueurs, int $barre, int $attendu): void
    {
        self::assertSame($attendu, OptimiseurCoupes::barres($longueurs, $barre));
    }

    /**
     * @param list<int> $longueurs
     */
    #[DataProvider('coupes')]
    public function test_le_calculateur_historique_donne_le_meme_nombre_de_barres(array $longueurs, int $barre, int $attendu): void
    {
        $standard = new Montant();
        $standard->setLongueur($barre);
        $standard->setPrix(1.0);
        $standard->setUniteParLot(1);
        $montants = array_map(static fn (int $h): MontantChantier => new MontantChantier('m', 0, 0, $h, false), $longueurs);

        $r = (new MateriauxCalculator())->calculer([], $montants, [], [], ['montant' => $standard], $standard);

        self::assertSame($attendu, $r['montant']['barres_necessaires']);
    }

    public function test_aucune_piece_aucune_barre(): void
    {
        self::assertSame(0, OptimiseurCoupes::barres([], 3000));
    }

    public function test_besoin_en_pieces_pour_un_article(): void
    {
        $article = new ArticleDouble('profil_ossature', ['longueur' => 3000, 'unites_par_lot' => 2]);
        $r = (new OptimiseurBesoins())->optimiser(LigneBesoin::pieces('m', 'ossature', 'profil_ossature', [2000, 1000, 2500]), $article);

        self::assertSame(2, $r->unitesAchetees);
        self::assertSame(1, $r->lots);
        self::assertSame(6000 - 5500, $r->chute);
    }

    public function test_un_autre_article_change_le_resultat(): void
    {
        $besoin = LigneBesoin::pieces('m', 'ossature', 'profil_ossature', [2000, 1000, 2500]);
        $court = (new OptimiseurBesoins())->optimiser($besoin, new ArticleDouble('profil_ossature', ['longueur' => 2500]));

        self::assertSame(3, $court->unitesAchetees);
        self::assertSame(2000, $court->chute);
    }

    public function test_besoin_en_surface_en_longueur_et_en_nombre(): void
    {
        $o = new OptimiseurBesoins();
        $plaque = ArticleDouble::plaque();

        $s = $o->optimiser(LigneBesoin::surface('p', 'parement', 'plaque_platre', 7_500_000), $plaque);
        self::assertSame(3, $s->unitesAchetees);
        self::assertSame(3 * 3_000_000 - 7_500_000, $s->chute);

        $l = $o->optimiser(LigneBesoin::pieces('j', 'x', 'bande_joint', []), new ArticleDouble('bande_joint', ['longueur' => 1000]));
        self::assertSame(0, $l->unitesAchetees);

        $n = $o->optimiser(LigneBesoin::nombre('v', 'x', 'visserie', 7), new ArticleDouble('visserie', ['unites_par_lot' => 5]));
        self::assertSame(2, $n->lots);
        self::assertSame(3, $n->chute);
    }
}
