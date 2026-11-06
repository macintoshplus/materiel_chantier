<?php

declare(strict_types=1);

namespace App\Tests\Calcul;

use App\Calcul\Calepinage\CalepinagePlaques;
use App\Calcul\Cadre\FourruresPlafond;
use App\Calcul\Couche\CalculateurCouche;
use App\Calcul\Optimisation\OptimiseurBesoins;
use App\Calcul\Couche\CoucheDecrite;
use App\Calcul\Couche\Cadre\CalculateurParement;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\Plafond\CalculateurOssaturePlafond;
use App\Calcul\Couche\Plafond\CalculateurSuspente;
use App\Calcul\Couche\PositionCouche;
use App\Calcul\Couche\RegistreCalculateurs;
use App\Calcul\Materiau\ResolveurMateriau;
use App\Calcul\Pipeline\PipelineCouches;
use App\Calcul\Pipeline\ResultatPipeline;
use App\Calcul\Pipeline\ValidateurSysteme;
use App\Calcul\Surface\SurfaceCalculable;
use App\Calcul\Systeme\SystemePlafondSuspendu;
use App\Catalogue\Categorie\ProfilOssature;
use App\Catalogue\Categorie\AccessoireOssature;
use App\Catalogue\Categorie\Isolant;
use App\Catalogue\Categorie\PlaquePlatre;
use App\Catalogue\Categorie\Suspente;
use App\Catalogue\Categorie\Visserie;
use App\Catalogue\RegistreCategories;
use App\Enum\TypeSurface;
use App\Geometrie\ContourIdentifie;
use App\Geometrie\Plan;
use App\Geometrie\Point3D;
use App\Tests\Calcul\Support\ArticleDouble;
use App\Tests\Calcul\Support\SourceCandidatsDouble;
use PHPUnit\Framework\TestCase;

final class PlafondSuspenduTest extends TestCase
{
    private function pan(int $largeur = 4000, int $profondeur = 3000, int $dz = 0): SurfaceCalculable
    {
        return new SurfaceCalculable(
            TypeSurface::PlafondPan,
            new Plan(new Point3D(0, 0, 2500), new Point3D(1000, 0, 0), new Point3D(0, 866, $dz)),
            ContourIdentifie::depuisTableau([
                ['id' => 'a', 'u' => 0, 'v' => 0],
                ['id' => 'b', 'u' => $largeur, 'v' => 0],
                ['id' => 'c', 'u' => $largeur, 'v' => $profondeur],
                ['id' => 'd', 'u' => 0, 'v' => $profondeur],
            ]),
        );
    }

    private function vis(int $longueur, int $min, int $max): ArticleDouble
    {
        return new ArticleDouble('visserie', ['usage' => 'plaque_sur_metal', 'longueur' => $longueur, 'pose.epaisseur_fixable_min' => $min, 'pose.epaisseur_fixable_max' => $max]);
    }

    private function plaque(int $entraxeMax = 600): ArticleDouble
    {
        return new ArticleDouble('plaque_platre', [
            'epaisseur' => 13, 'largeur' => 1200, 'longueur' => 2500,
            'pose.entraxe_max' => $entraxeMax, 'pose.pas_vis_premiere_peau' => 600,
            'pose.pas_vis_derniere_peau' => 300, 'pose.pas_vis_peau_unique' => 250,
        ]);
    }

    private function suspente(?int $pas = null): ArticleDouble
    {
        return new ArticleDouble('suspente', null === $pas ? [] : ['pas' => $pas]);
    }

    /**
     * @param array<string, scalar> $parametres
     * @param list<ArticleDouble>   $visserie
     */
    private function executer(SurfaceCalculable $pan, array $parametres = [], ?ArticleDouble $plaque = null, ?ArticleDouble $suspente = null, array $visserie = [], int $peaux = 1): ResultatPipeline
    {
        $lisse = new ArticleDouble('profil_ossature', ['famille' => 'lisse', 'longueur' => 3000]);
        $source = new SourceCandidatsDouble([$lisse, ...$visserie]);
        $categories = new RegistreCategories([new ProfilOssature(), new Visserie(), new Suspente(), new PlaquePlatre(), new Isolant(), new AccessoireOssature()]);
        $registre = new RegistreCalculateurs($this->calculateurs($source, $categories));
        $pipeline = new PipelineCouches($registre, new ResolveurMateriau($source), new ValidateurSysteme($registre));

        $couches = [
            new CoucheDecrite(0, 'suspente', 'attache', materiau: $suspente ?? $this->suspente()),
            new CoucheDecrite(1, 'ossature', 'ossature', materiau: new ArticleDouble('profil_ossature', ['famille' => 'fourrure', 'longueur' => 3000])),
        ];
        for ($i = 0; $i < $peaux; ++$i) {
            $couches[] = new CoucheDecrite(2 + $i, 'parement', 'parement', materiau: $plaque ?? $this->plaque(), position: PositionCouche::SurOssature);
        }

        return $pipeline->executer(new SystemePlafondSuspendu(), $pan, $couches, $parametres);
    }

    /**
     * @return list<CalculateurCouche>
     */
    private function calculateurs(SourceCandidatsDouble $source, RegistreCategories $categories): array
    {
        return [
            new CalculateurOssaturePlafond($source, $categories),
            new CalculateurSuspente(),
            new CalculateurParement(new CalepinagePlaques(), $source, $categories),
        ];
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
     * @return list<string>
     */
    private function codes(ResultatPipeline $r): array
    {
        return array_map(static fn ($a): string => $a->code, $r->avertissements);
    }

    public function test_les_fourrures_suivent_l_entraxe_limite_par_la_plaque(): void
    {
        $r = $this->executer($this->pan(), plaque: $this->plaque(400));

        self::assertTrue($r->estValide(), json_encode($r->erreurs, JSON_THROW_ON_ERROR));
        self::assertSame(400, $r->contexte->entraxeRetenu);
        self::assertCount(11, $this->ligne($r, 'fourrures')->pieces, 'une fourrure tous les 400 mm sur 4000 mm, plus la rive');
    }

    public function test_entraxe_utilisateur_au_dela_de_la_limite_conserve_avec_avertissement(): void
    {
        $r = $this->executer($this->pan(), ['entraxe' => 800], $this->plaque(400));

        self::assertSame(800, $r->contexte->entraxeRetenu);
        self::assertContains('pose_override_exceeds_limit', $this->codes($r));
    }

    public function test_les_suspentes_suivent_le_pas_de_l_article_et_portent_la_longueur_du_plenum(): void
    {
        $defaut = $this->ligne($this->executer($this->pan()), 'suspentes');
        $article = $this->ligne($this->executer($this->pan(), suspente: $this->suspente(900)), 'suspentes');
        $plenum = $this->ligne($this->executer($this->pan(), ['hauteur_plenum' => 350]), 'suspentes');

        self::assertSame(200, $defaut->caracteristiques['longueur_mm']);
        self::assertSame(350, $plenum->caracteristiques['longueur_mm']);
        self::assertGreaterThan($defaut->quantite, $article->quantite, 'pas de 900 < pas par defaut 1200');
    }

    public function test_lisse_sur_le_perimetre_sans_les_aretes_ouvertes(): void
    {
        $ferme = $this->ligne($this->executer($this->pan()), 'lisses_peripheriques');
        $ouvert = $this->ligne($this->executer($this->pan(), ['aretes_ouvertes' => 'a:b']), 'lisses_peripheriques');

        self::assertSame(14000, array_sum($ferme->pieces));
        self::assertSame(10000, array_sum($ouvert->pieces), 'l\'arete a:b (4000 mm) est ouverte');
    }

    public function test_pan_incline_longueurs_dans_le_plan_du_pan(): void
    {
        $r = $this->executer($this->pan(4000, 3000, 500));

        self::assertTrue($r->estValide());
        self::assertSame(14000, array_sum($this->ligne($r, 'lisses_peripheriques')->pieces), 'longueurs mesurees dans le plan du pan');
    }

    public function test_la_vis_la_plus_courte_compatible_est_retenue(): void
    {
        $r = $this->executer($this->pan(), visserie: [$this->vis(35, 0, 15), $this->vis(25, 0, 15), $this->vis(45, 0, 15)]);

        $ligne = $this->ligne($r, 'vis_2');
        self::assertSame(25, $ligne->materiau?->valeur('longueur'));
    }

    public function test_sans_vis_compatible_avertissement(): void
    {
        $r = $this->executer($this->pan(), visserie: [$this->vis(15, 0, 5)]);

        self::assertContains('screw_no_candidate', $this->codes($r));
    }

    public function test_pas_de_vis_utilisateur_prioritaire(): void
    {
        $vis = [$this->vis(25, 0, 15)];
        $defaut = $this->ligne($this->executer($this->pan(), visserie: $vis), 'vis_2');
        $serre = $this->ligne($this->executer($this->pan(), ['pas_vis_peau_unique' => 125], visserie: $vis), 'vis_2');

        self::assertGreaterThan($defaut->quantite, $serre->quantite);
    }

    public function test_deux_peaux_donnent_deux_vis_distinctes(): void
    {
        $r = $this->executer($this->pan(), visserie: [$this->vis(25, 0, 15), $this->vis(45, 0, 30)], peaux: 2);

        $longueurs = [];
        foreach ($r->lignes as $ligne) {
            if (str_starts_with($ligne->cle, 'vis_')) {
                $longueurs[] = $ligne->materiau?->valeur('longueur');
            }
        }
        self::assertSame([25, 45], $longueurs);
    }

    public function test_quantite_de_vis_sur_les_fourrures_rive_comprise_et_boites_entieres(): void
    {
        $pan = $this->pan();
        $lot = new ArticleDouble('visserie', ['usage' => 'plaque_sur_metal', 'longueur' => 25, 'pose.epaisseur_fixable_min' => 0, 'pose.epaisseur_fixable_max' => 15, 'unites_par_lot' => 100]);
        $r = $this->executer($pan, visserie: [$lot]);

        $fourrures = FourruresPlafond::longueurs($pan, $r->contexte->directionOssature, 600);
        $attendu = (int) ceil(array_sum($fourrures) / 250);
        $ligne = $this->ligne($r, 'vis_2');

        self::assertSame($attendu, $ligne->quantite, 'longueur des fourrures (rive comprise) / pas de la peau unique');
        $achat = (new OptimiseurBesoins())->optimiser($ligne, $lot);
        self::assertSame((int) ceil($attendu / 100), $achat->lots, 'boites entieres');
        self::assertGreaterThanOrEqual($attendu, $achat->lots * 100);
    }
}
