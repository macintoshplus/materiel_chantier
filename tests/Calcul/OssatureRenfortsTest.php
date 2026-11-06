<?php

declare(strict_types=1);

namespace App\Tests\Calcul;

use App\Calcul\Cadre\PlanificateurJoints;
use App\Calcul\Calepinage\CalepinagePlaques;
use App\Calcul\Couche\Cadre\CalculateurAttache;
use App\Calcul\Couche\Cadre\CalculateurIsolant;
use App\Calcul\Couche\Cadre\CalculateurOssature;
use App\Calcul\Couche\Cadre\CalculateurParement;
use App\Calcul\Couche\CoucheDecrite;
use App\Calcul\Couche\LigneBesoin;
use App\Calcul\Couche\PositionCouche;
use App\Calcul\Couche\RegistreCalculateurs;
use App\Calcul\Materiau\ResolveurMateriau;
use App\Calcul\Pipeline\PipelineCouches;
use App\Calcul\Pipeline\ResultatPipeline;
use App\Calcul\Pipeline\ValidateurSysteme;
use App\Calcul\Surface\SurfaceCalculable;
use App\Calcul\Systeme\SystemeDoublageOssature;
use App\Catalogue\Categorie\AccessoireOssature;
use App\Catalogue\Categorie\AppuiFixation;
use App\Catalogue\Categorie\Isolant;
use App\Catalogue\Categorie\PlaquePlatre;
use App\Catalogue\Categorie\ProfilOssature;
use App\Catalogue\Categorie\Suspente;
use App\Catalogue\Categorie\Visserie;
use App\Catalogue\RegistreCategories;
use App\Enum\TypeSurface;
use App\Tests\Calcul\Support\ArticleDouble;
use App\Tests\Calcul\Support\SourceCandidatsDouble;
use App\Tests\Calcul\Support\SurfacesCadre;
use PHPUnit\Framework\TestCase;

/**
 * Doublage sur ossature avec renforts : éclisses et vis d'assemblage du montant décalé, renfort localisé.
 */
final class OssatureRenfortsTest extends TestCase
{
    use SurfacesCadre;

    /**
     * @param list<array<string, mixed>> $portees
     * @param array<string, scalar>      $parametres
     */
    private function executer(SurfaceCalculable $mur, array $portees, array $parametres = []): ResultatPipeline
    {
        $montant = new ArticleDouble('profil_ossature', ['famille' => 'montant', 'serie' => 70, 'longueur' => 3000, 'pose.entraxe_defaut' => 600, 'pose.portees' => $portees]);
        $rail = new ArticleDouble('profil_ossature', ['famille' => 'rail', 'serie' => 70, 'longueur' => 3000]);
        $source = new SourceCandidatsDouble([$rail]);
        $categories = new RegistreCategories([new ProfilOssature(), new Visserie(), new Suspente(), new PlaquePlatre(), new Isolant(), new AccessoireOssature(), new AppuiFixation()]);
        $registre = new RegistreCalculateurs([
            new CalculateurOssature($source, $categories),
            new CalculateurAttache(),
            new CalculateurIsolant(),
            new CalculateurParement(new CalepinagePlaques(), $source, $categories),
        ]);
        $pipeline = new PipelineCouches($registre, new ResolveurMateriau($source), new ValidateurSysteme($registre));
        $couches = [
            new CoucheDecrite(0, 'attache', 'attache', materiau: ArticleDouble::attache()),
            new CoucheDecrite(1, 'ossature', 'ossature', materiau: $montant),
            new CoucheDecrite(2, 'isolant', 'isolant', materiau: ArticleDouble::isolant(0.035, 70), position: PositionCouche::EntreOssature),
            new CoucheDecrite(3, 'parement', 'parement', materiau: ArticleDouble::plaque(), position: PositionCouche::SurOssature),
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

    /**
     * @return list<int>
     */
    private function pieces(ResultatPipeline $r, string $cle): array
    {
        $ligne = $this->ligne($r, $cle);

        return null === $ligne ? [] : $ligne->pieces;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private static function portee(string $renfort, int $max, array $extra = []): array
    {
        return ['renfort' => $renfort, 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => $max, ...$extra];
    }

    public function test_double_decale_eclisses_par_joint_et_vis_d_assemblage_au_pas_fabricant(): void
    {
        $portees = [
            self::portee('simple', 2600),
            self::portee('double_decale', 4500, [
                'decalage_joints_min_mm' => 300,
                'pas_vis_assemblage_mm' => 400,
                'accessoires' => [['specification' => 'eclisse 70', 'par_joint' => 1]],
            ]),
        ];

        $r = $this->executer($this->mur(4000, 4000, type: TypeSurface::Mur), $portees);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        $montants = $this->ligne($r, 'montants') ?? self::fail('montants');
        // Chaque montant : deux lignes, 4000 aboutés (joints à 3000 et à 1500) = 4 segments.
        self::assertSame(0, \count($montants->pieces) % 4);
        $nombre = intdiv(\count($montants->pieces), 4);
        self::assertGreaterThan(0, $nombre);
        self::assertSame(1, $montants->caracteristiques['renfort_double']);
        $joints = array_sum(array_map('count', PlanificateurJoints::joints(4000, 3000, 300)));
        self::assertSame(2, $joints);
        self::assertSame($nombre * $joints, $this->ligne($r, 'eclisses')?->quantite, 'une éclisse par joint');
        self::assertSame($nombre * (int) ceil(4000 / 400), $this->ligne($r, 'vis_assemblage')?->quantite, 'vis au pas fabricant sur toute la longueur');
        self::assertSame(4000 * 2 * $nombre, array_sum($montants->pieces), 'doublé sur toute la longueur');
    }

    public function test_montant_court_double_decale_sans_joint_ni_eclisse(): void
    {
        $portees = [
            self::portee('simple', 2000),
            self::portee('double_decale', 3000, ['decalage_joints_min_mm' => 300, 'pas_vis_assemblage_mm' => 400, 'accessoires' => [['specification' => 'eclisse 70', 'par_joint' => 1]]]),
        ];

        $r = $this->executer($this->mur(4000, 2800, type: TypeSurface::Mur), $portees);

        self::assertTrue($r->estValide(), json_encode($r->erreurs, \JSON_THROW_ON_ERROR));
        self::assertNull($this->ligne($r, 'eclisses'));
        self::assertNotNull($this->ligne($r, 'vis_assemblage'));
        self::assertSame(1, $this->ligne($r, 'montants')?->caracteristiques['renfort_double']);
    }

    public function test_renfort_localise_aux_bords_d_ouverture(): void
    {
        $portees = [self::portee('simple', 3500), self::portee('double_accole', 4000)];
        $mur = $this->mur(4000, 3000, ouvertures: [$this->ouverture(1000, 0, 900, 2040)], type: TypeSurface::Mur);

        $base = $this->executer($mur, $portees);
        $tous = $this->executer($mur, $portees, ['renfort' => 'double_accole']);
        $bords = $this->executer($mur, $portees, ['renfort' => 'double_accole', 'renfort_zone' => 'bords_ouvertures']);

        $n = \count($this->pieces($base, 'montants'));
        self::assertTrue($bords->estValide(), json_encode($bords->erreurs, \JSON_THROW_ON_ERROR));
        self::assertSame(2 * $n, \count($this->pieces($tous, 'montants')), 'zone par défaut : tous les montants doublés');
        $doubles = \count($this->pieces($bords, 'montants')) - $n;
        self::assertGreaterThan(0, $doubles);
        self::assertLessThan($n, $doubles, 'seuls les montants des bords sont doublés');
    }

    public function test_renfort_localise_avec_montant_non_renforce_trop_long_est_bloquant(): void
    {
        $portees = [self::portee('simple', 2600), self::portee('double_accole', 4000)];
        $mur = $this->mur(4000, 3000, ouvertures: [$this->ouverture(1000, 0, 900, 2040)], type: TypeSurface::Mur);

        $r = $this->executer($mur, $portees, ['renfort' => 'double_accole', 'renfort_zone' => 'bords_ouvertures']);

        self::assertFalse($r->estValide());
        self::assertSame('framing_unreinforced_stud_too_long', $r->erreurs[0]->code);
    }
}
