<?php

declare(strict_types=1);

namespace App\Tests\Calcul;

use App\Calcul\Calepinage\CalepinagePlaques;
use App\Calcul\Couche\Cadre\CalculateurAttache;
use App\Calcul\Couche\Cadre\CalculateurIsolant;
use App\Calcul\Couche\Cadre\CalculateurOssature;
use App\Calcul\Couche\Cadre\CalculateurParement;
use App\Calcul\Couche\CoucheDecrite;
use App\Calcul\Couche\Finition\CalculateurBandeJoint;
use App\Calcul\Couche\Finition\CalculateurBandeResiliente;
use App\Calcul\Couche\Finition\CalculateurEnduit;
use App\Calcul\Couche\Finition\CalculateurFixationSupport;
use App\Calcul\Couche\Finition\CalculateurMembrane;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\PositionCouche;
use App\Calcul\Couche\RegistreCalculateurs;
use App\Calcul\Materiau\ResolveurMateriau;
use App\Calcul\Optimisation\OptimiseurBesoins;
use App\Calcul\Pipeline\PipelineCouches;
use App\Calcul\Pipeline\ResultatPipeline;
use App\Calcul\Pipeline\ValidateurSysteme;
use App\Calcul\Surface\SurfaceCalculable;
use App\Calcul\Systeme\SystemeDoublageOssature;
use App\Calcul\Thermique\ThermiqueCalculator;
use App\Catalogue\Categorie\AccessoireOssature;
use App\Catalogue\Categorie\AppuiFixation;
use App\Catalogue\Categorie\Isolant;
use App\Catalogue\Categorie\PlaquePlatre;
use App\Catalogue\Categorie\ProfilOssature;
use App\Catalogue\Categorie\Suspente;
use App\Catalogue\Categorie\Visserie;
use App\Catalogue\RegistreCategories;
use App\Enum\TypePose;
use App\Enum\TypeSurface;
use App\Tests\Calcul\Support\ArticleDouble;
use App\Tests\Calcul\Support\SourceCandidatsDouble;
use App\Tests\Calcul\Support\SurfacesCadre;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Étape 05d : retour de parement, thermique par couche, enduit, membrane, chevilles et bande résiliente, sur un
 * doublage (montants série 70, 13 mm de plaque) dont les quantités sont recalculées à la main.
 */
final class FinitionsThermiqueTest extends TestCase
{
    use SurfacesCadre;

    private const int ORDRE_ISOLANT = 2;

    /**
     * @param list<CoucheDecrite>   $finitions
     * @param array<string, scalar> $parametres
     */
    private function executer(SurfaceCalculable $mur, array $finitions = [], array $parametres = [], int $isolant = 70, int $serie = 70): ResultatPipeline
    {
        $montant = new ArticleDouble('profil_ossature', ['famille' => 'montant', 'serie' => $serie, 'longueur' => 3000, 'pose.entraxe_defaut' => 600, 'pose.portees' => [['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 3500]]]);
        $rail = new ArticleDouble('profil_ossature', ['famille' => 'rail', 'serie' => $serie, 'longueur' => 3000]);
        $source = new SourceCandidatsDouble([$rail]);
        $categories = new RegistreCategories([new ProfilOssature(), new Visserie(), new Suspente(), new PlaquePlatre(), new Isolant(), new AccessoireOssature(), new AppuiFixation()]);
        $registre = new RegistreCalculateurs([
            new CalculateurOssature($source, $categories),
            new CalculateurAttache(),
            new CalculateurIsolant(),
            new CalculateurParement(new CalepinagePlaques(), $source, $categories),
            new CalculateurEnduit(),
            new CalculateurMembrane(),
            new CalculateurBandeJoint(),
            new CalculateurFixationSupport(),
            new CalculateurBandeResiliente(),
        ]);
        $pipeline = new PipelineCouches($registre, new ResolveurMateriau($source), new ValidateurSysteme($registre));
        $couches = [
            new CoucheDecrite(0, 'attache', 'attache', materiau: ArticleDouble::attache()),
            new CoucheDecrite(1, 'ossature', 'ossature', materiau: $montant),
            new CoucheDecrite(self::ORDRE_ISOLANT, 'isolant', 'isolant', materiau: ArticleDouble::isolant(0.035, $isolant), position: PositionCouche::EntreOssature),
            new CoucheDecrite(3, 'parement', 'parement', materiau: ArticleDouble::plaque(), position: PositionCouche::SurOssature),
            ...$finitions,
        ];

        return $pipeline->executer(new SystemeDoublageOssature(), $mur, $couches, $parametres);
    }

    private function ligne(ResultatPipeline $r, string $cle): ?LigneBesoin
    {
        foreach ($r->lignes as $ligne) {
            if ($ligne->cle === $cle) {
                return $ligne;
            }
        }

        return null;
    }

    private function quantite(ResultatPipeline $r, string $cle): int
    {
        return ($this->ligne($r, $cle) ?? self::fail('Missing line '.$cle))->quantite;
    }

    /**
     * @return list<string>
     */
    private function codes(ResultatPipeline $r): array
    {
        return array_map(static fn ($a): string => $a->code, $r->avertissements);
    }

    private function enduit(int $ordre, string $emplacement, string $usage, float $consommation, ?int $epaisseur = null, int $sac = 25): CoucheDecrite
    {
        $cle = 'joint' === $usage ? 'consommation_kg_par_m2' : 'consommation_kg_par_m2_par_mm';

        return new CoucheDecrite($ordre, $emplacement, 'enduit', materiau: new ArticleDouble('enduit', ['usage' => $usage, $cle => $consommation, 'unites_par_lot' => $sac]), epaisseurOverride: $epaisseur);
    }

    public function test_r_total_du_mur_de_test_conforme_au_calcul_manuel(): void
    {
        $r = $this->executer($this->mur(4000, 2500, type: TypeSurface::Mur), isolant: 100, serie: 100);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        // Laine 100 mm, lambda 0,035 : R = 0,1 / 0,035 = 2,857 ; la plaque BA13 n'a pas de lambda au catalogue : R = 0.
        self::assertSame([self::ORDRE_ISOLANT => 2857], $r->resistances);

        $bilan = (new ThermiqueCalculator())->resistanceTotale(TypeSurface::Mur, 500, $r->resistances);

        // RT = Rsi 0,13 + paroi 0,5 + 2,857 + Rse 0,04 = 3,527 ; U = 1 / 3,527 = 0,2835, arrondi à 284 millièmes.
        self::assertSame(3527, $bilan->rTotaleMilli);
        self::assertSame(284, $bilan->uMilli);
        self::assertFalse($bilan->deuxCotesInterieurs);
        self::assertSame(130, $bilan->rsiMilli);
        self::assertSame(40, $bilan->rCoteOpposeMilli);
        self::assertSame(-473, $bilan->deltaObjectifMilli(4000));
    }

    public function test_cloison_rsi_des_deux_cotes_sans_rse(): void
    {
        $bilan = (new ThermiqueCalculator())->resistanceTotale(TypeSurface::CloisonFace, 0, [self::ORDRE_ISOLANT => 2857, 4 => 0]);

        // 0,13 + 2,857 + 0,13 = 3,117 ; U = 0,321.
        self::assertSame(3117, $bilan->rTotaleMilli);
        self::assertSame(321, $bilan->uMilli);
        self::assertSame(130, $bilan->rsiMilli);
        self::assertSame(130, $bilan->rCoteOpposeMilli);
        self::assertTrue($bilan->deuxCotesInterieurs);
        self::assertSame([self::ORDRE_ISOLANT => 2857, 4 => 0], $bilan->rCouchesMilli);
    }

    public function test_tunnel_ajoute_des_bandes_de_retour_aux_plaques_vis_et_joints_et_applique_rien(): void
    {
        $finitions = [new CoucheDecrite(4, 'bande_joint', 'bande_joint', materiau: new ArticleDouble('bande_joint', ['type' => 'armee']))];
        $applique = $this->executer($this->mur(4000, 2500, ouvertures: [$this->ouverture(500, 0, 1000, 2100, 'porte', TypePose::Applique)], type: TypeSurface::Mur), $finitions);
        $tunnel = $this->executer($this->mur(4000, 2500, ouvertures: [$this->ouverture(500, 0, 1000, 2100, 'porte', TypePose::Tunnel)], type: TypeSurface::Mur), $finitions);

        self::assertTrue($tunnel->estValide(), json_encode($tunnel->erreurs, \JSON_THROW_ON_ERROR));
        $plaquesApplique = $this->ligne($applique, 'plaques_3') ?? self::fail('plaques');
        $plaquesTunnel = $this->ligne($tunnel, 'plaques_3') ?? self::fail('plaques');
        self::assertArrayNotHasKey('retour_plaques', $plaquesApplique->caracteristiques);
        self::assertNull($this->ligne($applique, 'bande_angles_sortants_4'));

        // Profondeur : 70 mm d'ossature + 13 mm de plaque. Linteau 1000 + côtés 2 x 2100, sans appui (porte au sol).
        self::assertSame(83, $plaquesTunnel->caracteristiques['retour_profondeur_mm']);
        $retour = $plaquesTunnel->caracteristiques['retour_plaques'];
        self::assertGreaterThan(0, $retour);
        self::assertSame($plaquesApplique->quantite + $retour, $plaquesTunnel->quantite);
        // Une rangée de vis au pas de la dernière peau (peau unique : 250) le long de chaque bande.
        $visRetour = (int) ceil(1000 / 250) + 2 * (int) ceil(2100 / 250);
        self::assertSame($visRetour, $this->ligne($tunnel, 'vis_3')?->caracteristiques['retour_vis']);
        self::assertSame($this->quantite($applique, 'vis_3') + $visRetour, $this->quantite($tunnel, 'vis_3'));
        // Angles sortants : longueur du contour du retour (bande armée), en mm.
        self::assertSame(1000 + 2 * 2100, $this->ligne($tunnel, 'bande_angles_sortants_4')?->quantite);
        self::assertSame([], $this->codes($tunnel));
    }

    public function test_fenetre_en_tunnel_ajoute_la_bande_d_appui(): void
    {
        $porte = $this->executer($this->mur(4000, 2500, ouvertures: [$this->ouverture(500, 0, 1000, 1200, 'porte')], type: TypeSurface::Mur));
        $fenetre = $this->executer($this->mur(4000, 2500, ouvertures: [$this->ouverture(500, 900, 1000, 1200, 'fenetre')], type: TypeSurface::Mur));

        $surfaceBandes = static fn (ResultatPipeline $r): int => $r->contexte->surfaceParementMm2 - (4000 * 2500 - 1000 * 1200);

        // Porte : linteau + 2 côtés ; fenêtre : linteau + 2 côtés + appui, sur 83 mm.
        self::assertSame((1000 + 2 * 1200) * 83, $surfaceBandes($porte));
        self::assertSame((2 * 1000 + 2 * 1200) * 83, $surfaceBandes($fenetre));
    }

    public function test_changer_le_mode_de_pose_change_les_quantites_sans_autre_modification(): void
    {
        $quantites = [];
        foreach ([TypePose::Applique, TypePose::Tunnel, TypePose::Applique] as $pose) {
            $r = $this->executer($this->mur(4000, 2500, ouvertures: [$this->ouverture(500, 0, 1000, 2100, 'porte', $pose)], type: TypeSurface::Mur));
            $quantites[] = [$this->quantite($r, 'plaques_3'), $this->quantite($r, 'vis_3')];
        }

        self::assertNotSame($quantites[0], $quantites[1]);
        self::assertGreaterThan($quantites[0][0], $quantites[1][0]);
        self::assertGreaterThan($quantites[0][1], $quantites[1][1]);
        self::assertSame($quantites[0], $quantites[2], 'revenir en applique restitue les quantités initiales');
    }

    public function test_profondeur_imposee_au_dela_de_la_capacite_de_l_ossature_avertit(): void
    {
        $mur = $this->mur(4000, 2500, ouvertures: [$this->ouverture(500, 0, 1000, 2100)], type: TypeSurface::Mur);

        $r = $this->executer($mur, parametres: ['retour_profondeur' => 120]);

        self::assertSame(120, $this->ligne($r, 'plaques_3')?->caracteristiques['retour_profondeur_mm']);
        // Capacité : 70 + 13 mm ; 120 mm demandés dépassent ce que l'ossature d'encadrement porte.
        self::assertSame(['reveal_deeper_than_framing'], $this->codes($r));
    }

    /**
     * @return iterable<string, array{string, float, ?int, int}>
     */
    public static function enduits(): iterable
    {
        // Surface de plaque : 4000 x 2500 = 10 m2.
        yield 'joint seul, 0,35 kg/m2' => ['joint', 0.35, null, 4];
        yield 'ratissage 2 mm, 1,2 kg/m2/mm' => ['ratissage', 1.2, 2, 24];
        yield 'ratissage 5 mm' => ['ratissage', 1.2, 5, 60];
    }

    #[DataProvider('enduits')]
    public function test_enduit_par_surface_de_plaque_arrondi_au_kilo_puis_au_sac(string $usage, float $consommation, ?int $epaisseur, int $kilos): void
    {
        $emplacement = 'joint' === $usage ? 'enduit_joint' : 'enduit_ratissage';
        $couche = $this->enduit(4, $emplacement, $usage, $consommation, $epaisseur);

        $r = $this->executer($this->mur(4000, 2500, type: TypeSurface::Mur), [$couche]);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        $ligne = $this->ligne($r, 'joint' === $usage ? 'enduit_joint_4' : 'ratissage_4') ?? self::fail('enduit');
        self::assertSame($kilos, $ligne->quantite);
        $optimise = (new OptimiseurBesoins())->optimiser($ligne, $couche->materiau ?? self::fail('materiau'));
        self::assertSame((int) ceil($kilos / 25), $optimise->lots);
    }

    public function test_sans_ratissage_seul_le_joint_est_compte(): void
    {
        $r = $this->executer($this->mur(4000, 2500, type: TypeSurface::Mur), [$this->enduit(4, 'enduit_joint', 'joint', 0.35)]);

        self::assertNotNull($this->ligne($r, 'enduit_joint_4'));
        self::assertNull($this->ligne($r, 'ratissage_4'));
    }

    public function test_ratissage_sans_epaisseur_est_une_erreur_et_usage_inattendu_avertit(): void
    {
        $r = $this->executer($this->mur(4000, 2500, type: TypeSurface::Mur), [$this->enduit(4, 'enduit_ratissage', 'ratissage', 1.2)]);
        self::assertFalse($r->estValide());
        self::assertSame('plaster_thickness_missing', $r->erreurs[0]->code);

        $r = $this->executer($this->mur(4000, 2500, type: TypeSurface::Mur), [$this->enduit(4, 'enduit_joint', 'ratissage', 0.35)]);
        self::assertContains('plaster_usage_mismatch', $this->codes($r));
    }

    public function test_l_enduit_compte_les_retours_en_tunnel(): void
    {
        $mur = $this->mur(4000, 2500, ouvertures: [$this->ouverture(500, 0, 1000, 2100)], type: TypeSurface::Mur);

        $r = $this->executer($mur, [$this->enduit(4, 'enduit_joint', 'joint', 1.0)]);

        // Surface nette 10 m2 - 2,1 m2 d'ouverture + bandes (1000 + 2 x 2100) x 83 = 0,4316 m2 : ceil(8,3316) kg.
        self::assertSame(9, $this->quantite($r, 'enduit_joint_4'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int}>
     */
    public static function membranes(): iterable
    {
        // Mur 4000 x 2500 = 10 m2 ; rouleau 1500 x 50000 mm, recouvrement 100 : surface utile 1400 x 50000 = 70 m2.
        yield 'un rouleau' => [['largeur' => 1500, 'longueur' => 50000, 'recouvrement_mm' => 100], 1];
        // Rouleau 1500 x 5000, recouvrement 100 : 1400 x 5000 = 7 m2 -> 2 rouleaux.
        yield 'deux rouleaux' => [['largeur' => 1500, 'longueur' => 5000, 'recouvrement_mm' => 100], 2];
        // Sans recouvrement au catalogue : 100 mm par défaut.
        yield 'recouvrement par défaut' => [['largeur' => 1500, 'longueur' => 5000], 2];
    }

    /**
     * @param array<string, mixed> $valeurs
     */
    #[DataProvider('membranes')]
    public function test_membrane_en_rouleaux_de_surface_utile_et_adhesif_en_longueur(array $valeurs, int $rouleaux): void
    {
        $couche = new CoucheDecrite(4, 'membrane', 'membrane', materiau: new ArticleDouble('membrane', $valeurs));

        $r = $this->executer($this->mur(4000, 2500, type: TypeSurface::Mur), [$couche]);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        self::assertSame($rouleaux, $this->quantite($r, 'membrane_4'));
        // Lés verticaux de 1400 mm utiles : ceil(4000 / 1400) = 3 lés, donc 2 jonctions de 2500, plus la périphérie de 13000.
        self::assertSame(2 * 2500 + 2 * (4000 + 2500), $this->quantite($r, 'adhesif_membrane_4'));
        self::assertArrayNotHasKey(4, $r->resistances, 'la membrane ne porte aucune résistance');
    }

    public function test_membrane_sans_dimension_de_rouleau_avertit(): void
    {
        $couche = new CoucheDecrite(4, 'membrane', 'membrane', materiau: new ArticleDouble('membrane', []));

        $r = $this->executer($this->mur(4000, 2500, type: TypeSurface::Mur), [$couche]);

        self::assertFalse($r->estValide());
        self::assertSame('membrane_roll_missing', $r->erreurs[0]->code);
    }

    public function test_chevilles_longueur_de_profile_sur_pas_plus_peripherie(): void
    {
        $couche = new CoucheDecrite(4, 'fixation_support', 'fixation_support', materiau: new ArticleDouble('fixation_support', ['pose.pas_mm' => 500]));

        $r = $this->executer($this->mur(4000, 2500, type: TypeSurface::Mur), [$couche]);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        // Rails bas et haut de 4000 mm : 2 x ceil(4000 / 500) = 16 chevilles, plus 2 par rail de périphérie.
        $ligne = $this->ligne($r, 'fixations_support') ?? self::fail('fixations');
        self::assertSame($r->contexte->railsSupport, [4000, 4000]);
        self::assertSame(16 + 4, $ligne->quantite);
        self::assertSame(4, $ligne->caracteristiques['peripherie']);
    }

    public function test_chevilles_pas_deduit_du_nombre_par_metre(): void
    {
        $couche = new CoucheDecrite(4, 'fixation_support', 'fixation_support', materiau: new ArticleDouble('fixation_support', ['pose.nombre_par_m' => 2.0]));

        $r = $this->executer($this->mur(4000, 2500, type: TypeSurface::Mur), [$couche]);

        self::assertSame(500, $this->ligne($r, 'fixations_support')?->caracteristiques['pas_mm']);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function bandesResilientes(): iterable
    {
        // Rails de 2 x 4000 = 8000 mm ; rouleau de 10000 mm.
        yield 'marge nulle' => [0, 1];
        yield 'marge 10 %, 8800 mm' => [10, 1];
        yield 'marge 30 %, 10400 mm' => [30, 2];
    }

    #[DataProvider('bandesResilientes')]
    public function test_bande_resiliente_rouleaux_avec_marge(int $marge, int $rouleaux): void
    {
        $couche = new CoucheDecrite(4, 'bande_resiliente', 'bande_resiliente', materiau: new ArticleDouble('bande_resiliente', ['longueur_rouleau' => 10000]));

        $r = $this->executer($this->mur(4000, 2500, type: TypeSurface::Mur), [$couche], ['marge_bande_resiliente_pct' => $marge]);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        self::assertSame($rouleaux, $this->quantite($r, 'bande_resiliente'));
    }

    public function test_bande_de_joint_rentrante_pour_les_angles_retour_contre_retour(): void
    {
        $couche = new CoucheDecrite(4, 'bande_joint', 'bande_joint', materiau: new ArticleDouble('bande_joint', ['type' => 'plat', 'longueur_rouleau' => 30000]));
        $mur = $this->mur(4000, 2500, ouvertures: [$this->ouverture(500, 0, 1000, 2100)], type: TypeSurface::Mur);

        $r = $this->executer($mur, [$couche]);

        // Porte au sol : deux angles rentrants (linteau/côté) de 83 mm, en un seul rouleau.
        $ligne = $this->ligne($r, 'bande_angles_rentrants_4') ?? self::fail('bande');
        self::assertSame(1, $ligne->quantite);
        self::assertSame(2 * 83, $ligne->caracteristiques['longueur_mm']);
    }

    public function test_sans_couche_de_finition_aucune_ligne_de_finition(): void
    {
        $r = $this->executer($this->mur(4000, 2500, ouvertures: [$this->ouverture(500, 0, 1000, 2100, 'porte', TypePose::Applique)], type: TypeSurface::Mur));

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        $cles = array_map(static fn (LigneBesoin $l): string => $l->cle, $r->lignes);
        foreach (['fixations_support', 'bande_resiliente', 'enduit_joint_4', 'ratissage_4', 'membrane_4'] as $cle) {
            self::assertNotContains($cle, $cles);
        }
        self::assertSame([], $this->codes($r));
    }
}
