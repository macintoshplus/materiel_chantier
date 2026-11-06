<?php

declare(strict_types=1);

namespace App\Tests\Calcul;

use App\Calcul\Couche\CoucheDecrite;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\PositionCouche;
use App\Calcul\Pipeline\ResultatPipeline;
use App\Catalogue\Categorie\ProfilOssature;
use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Geometrie\Avertissement;
use App\Geometrie\Direction;
use App\Tests\Calcul\Support\ArticleDouble;
use App\Tests\Calcul\Support\ScenarioCouches;
use App\Tests\Calcul\Support\SourceCandidatsDouble;
use App\Tests\Calcul\Systemes\SystemeIsolantsCroises;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PipelineCouchesTest extends TestCase
{
    use ScenarioCouches;

    /**
     * @param array<int, CoucheDecrite> $couches
     */
    private function executer(array $couches): ResultatPipeline
    {
        return $this->pipeline(new SourceCandidatsDouble([]))->executer(new SystemeIsolantsCroises(), $this->surface(), array_values($couches));
    }

    private function ligne(ResultatPipeline $r, string $cle): LigneBesoin
    {
        foreach ($r->lignes as $ligne) {
            if ($ligne->cle === $cle) {
                return $ligne;
            }
        }
        self::fail('Missing line '.$cle);
    }

    /**
     * @param list<Avertissement> $liste
     *
     * @return list<string>
     */
    private function codes(array $liste): array
    {
        return array_map(static fn (Avertissement $a): string => $a->code, $liste);
    }

    public function test_les_couches_s_enchainent_et_les_epaisseurs_se_cumulent(): void
    {
        $r = $this->executer($this->couchesCroisees());

        self::assertTrue($r->estValide());
        self::assertSame([], $r->avertissements);
        // 2 isolants de 60 mm + 2 plaques de 13 mm.
        self::assertSame(120 + 26, $r->contexte->epaisseurCumulee);
        self::assertSame(120, $r->contexte->epaisseurIsoleePrevue);
        self::assertSame(120, $r->contexte->profondeurCavite);
        self::assertSame(26, $r->contexte->epaisseurParementPrevue);
        self::assertSame(26, $r->contexte->epaisseurParementCumulee);
    }

    public function test_l_epaisseur_cumulee_des_isolants_pilote_attaches_et_vis(): void
    {
        $r = $this->executer($this->couchesCroisees());

        self::assertSame(120, $this->ligne($r, 'attaches')->caracteristiques['longueur_mm']);
        // Vis de la 1re peau : 13 + 10 ; de la 2e : (13 + 13) + 10.
        self::assertSame(23, $this->ligne($r, 'vis_4')->caracteristiques['longueur_mm']);
        self::assertSame(36, $this->ligne($r, 'vis_5')->caracteristiques['longueur_mm']);
        self::assertSame(150, $this->ligne($r, 'montants')->caracteristiques['profondeur_mm']);
    }

    public function test_le_r_total_additionne_les_deux_isolants(): void
    {
        $r = $this->executer($this->couchesCroisees());

        // 60 mm a lambda 0,035 : 1,7143 m2.K/W, soit 1714 ; deux couches : 3428 (3,4286 en exact).
        self::assertSame(3428, $r->contexte->rCumuleMilli);
        self::assertEqualsWithDelta(3.4286, $r->contexte->rCumuleMilli / 1000, 0.001);
    }

    public function test_une_ossature_moins_profonde_que_l_isolant_avertit(): void
    {
        $r = $this->executer($this->couchesCroisees(ArticleDouble::profil(100)));

        self::assertSame(['insulation_deeper_than_framing'], $this->codes($r->avertissements));
    }

    public function test_la_deuxieme_isolation_a_90_est_coupee_perpendiculairement_a_l_ossature(): void
    {
        $r = $this->executer($this->couchesCroisees());

        $parallele = $this->ligne($r, 'isolant_1');
        $croisee = $this->ligne($r, 'isolant_2');
        self::assertSame(Direction::Verticale, $parallele->direction);
        self::assertSame(Direction::Horizontale, $croisee->direction);
        // Mur 3000 x 2500, ossature verticale : bandes de 2500 (parallèles) contre 3000 (perpendiculaires).
        self::assertSame([2500], array_values(array_unique($parallele->pieces)));
        self::assertSame([3000], array_values(array_unique($croisee->pieces)));
    }

    public function test_les_joints_de_la_deuxieme_peau_sont_decales_d_au_moins_40_cm(): void
    {
        $r = $this->executer($this->couchesCroisees());

        self::assertNotContains('layer_joints_too_close', $this->codes($r->avertissements));
        self::assertNotNull($r->contexte->calepinagePrecedent);
        // Peau 1 : joints à 1200 et 2400 ; peau 2 décalée d'une demi-plaque : 600 et 1800.
        self::assertSame([600, 1800], $r->contexte->calepinagePrecedent->jointsVerticaux());
    }

    public function test_des_joints_alignes_declenchent_un_avertissement(): void
    {
        $r = $this->executer($this->couchesCroisees(null, 0));

        self::assertSame(['layer_joints_too_close'], $this->codes($r->avertissements));
        self::assertSame(0, $r->avertissements[0]->params['actual']);
        self::assertSame(400, $r->avertissements[0]->params['minimum']);
    }

    public function test_une_couche_non_epinglee_est_resolue_par_sa_specification_et_le_contexte(): void
    {
        $profils = new ProfilOssature();
        $specification = MaterielSpecification::creer('ossature', $profils, [new Critere('serie', Operateur::SuperieurOuEgal, '{contexte.profondeur_cavite}')]);
        $source = new SourceCandidatsDouble([ArticleDouble::profil(100), ArticleDouble::profil(150)]);
        $couches = $this->couchesCroisees();
        $couches[3] = new CoucheDecrite(3, 'ossature', 'ossature', specification: $specification);

        $r = $this->pipeline($source)->executer(new SystemeIsolantsCroises(), $this->surface(), array_values($couches));

        self::assertTrue($r->estValide());
        self::assertSame(150, $r->materiaux[3]?->valeur('serie'));
        self::assertSame(120, $source->demandes[0]->criteres[0]->valeur);
        self::assertSame([], $r->avertissements);
    }

    public function test_une_couche_sans_candidat_est_une_erreur_bloquante(): void
    {
        $profils = new ProfilOssature();
        $specification = MaterielSpecification::creer('ossature', $profils, [new Critere('serie', Operateur::SuperieurOuEgal, 500)]);
        $couches = $this->couchesCroisees();
        $couches[3] = new CoucheDecrite(3, 'ossature', 'ossature', specification: $specification);

        $r = $this->pipeline(new SourceCandidatsDouble([ArticleDouble::profil(100)]))->executer(new SystemeIsolantsCroises(), $this->surface(), array_values($couches));

        self::assertSame(['layer_no_candidate'], $this->codes($r->erreurs));
        self::assertSame([], $r->lignes);
    }

    public function test_un_article_epingle_qui_ne_satisfait_pas_la_specification_avertit_mais_est_conserve(): void
    {
        $profils = new ProfilOssature();
        $specification = MaterielSpecification::creer('ossature', $profils, [new Critere('serie', Operateur::SuperieurOuEgal, '{contexte.profondeur_cavite}')]);
        $couches = $this->couchesCroisees();
        $couches[3] = new CoucheDecrite(3, 'ossature', 'ossature', specification: $specification, materiau: ArticleDouble::profil(100));

        $r = $this->executer($couches);

        self::assertTrue($r->estValide());
        self::assertSame(100, $r->materiaux[3]?->valeur('serie'));
        self::assertContains('pinned_material_mismatch', $this->codes($r->avertissements));
    }

    /**
     * @return iterable<string, array{callable(array<int, CoucheDecrite>): array<int, CoucheDecrite>, string}>
     */
    public static function erreursDeValidation(): iterable
    {
        yield 'emplacement inconnu' => [static fn (array $c): array => [...$c, new CoucheDecrite(9, 'inconnu', 'x', materiau: ArticleDouble::attache())], 'layer_slot_unknown'];
        yield 'sans ossature (cardinalité)' => [static fn (array $c): array => array_values(array_filter($c, static fn (CoucheDecrite $d): bool => 'ossature' !== $d->emplacement)), 'layer_cardinality'];
        yield 'trois isolants (cardinalité)' => [static fn (array $c): array => [...$c, new CoucheDecrite(9, 'isolant', 'isolant', materiau: ArticleDouble::isolant(0.035, 45), position: PositionCouche::Continue)], 'layer_cardinality'];
        yield 'rôle différent' => [static fn (array $c): array => [...$c, new CoucheDecrite(9, 'attache', 'autre', materiau: ArticleDouble::attache())], 'layer_role_mismatch'];
        yield 'croisée entre ossature' => [static fn (array $c): array => [$c[0], new CoucheDecrite(1, 'isolant', 'isolant', materiau: ArticleDouble::isolant(0.035, 60), orientation: 90, position: PositionCouche::EntreOssature), ...\array_slice($c, 2)], 'crossed_layer_between_framing'];
        yield 'orientation interdite' => [static fn (array $c): array => [$c[0], new CoucheDecrite(1, 'isolant', 'isolant', materiau: ArticleDouble::isolant(0.035, 60), orientation: 45, position: PositionCouche::Continue), ...\array_slice($c, 2)], 'layer_orientation_not_allowed'];
        yield 'position interdite' => [static fn (array $c): array => [...\array_slice($c, 0, 4), new CoucheDecrite(4, 'parement', 'parement', materiau: ArticleDouble::plaque(), position: PositionCouche::Continue), $c[5]], 'layer_position_not_allowed'];
        yield 'ni matériau ni spécification' => [static fn (array $c): array => [...\array_slice($c, 0, 3), new CoucheDecrite(3, 'ossature', 'ossature'), ...\array_slice($c, 4)], 'layer_without_material'];
    }

    /**
     * @param callable(array<int, CoucheDecrite>): array<int, CoucheDecrite> $modifier
     */
    #[DataProvider('erreursDeValidation')]
    public function test_les_erreurs_de_validation_bloquent_le_calcul(callable $modifier, string $code): void
    {
        $r = $this->executer($modifier($this->couchesCroisees()));

        self::assertFalse($r->estValide());
        self::assertContains($code, $this->codes($r->erreurs));
        self::assertSame([], $r->lignes);
    }
}
