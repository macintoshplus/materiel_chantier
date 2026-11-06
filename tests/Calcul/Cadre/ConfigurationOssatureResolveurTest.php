<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Cadre;

use App\Calcul\Cadre\ConfigurationOssature;
use App\Calcul\Cadre\ConfigurationOssatureResolveur;
use App\Calcul\Cadre\StrategieConfiguration;
use App\Calcul\Pose\OrientationPose;
use App\Catalogue\Vue\Orientation;
use App\Catalogue\Vue\ProfilView;
use App\Catalogue\Vue\Renfort;
use App\Tests\Calcul\Support\ArticleDouble;
use PHPUnit\Framework\TestCase;

final class ConfigurationOssatureResolveurTest extends TestCase
{
    private function profil(int $serie = 48): ProfilView
    {
        $ligne = static fn (string $renfort, string $orientation, int $entraxe, int $max): array => ['renfort' => $renfort, 'orientation' => $orientation, 'entraxe' => $entraxe, 'longueur_max' => $max];

        return new ProfilView(new ArticleDouble('profil_ossature', [
            'serie' => $serie,
            'pose.entraxe_defaut' => 600,
            'pose.portees' => [
                $ligne('simple', 'verticale', 600, 2600),
                $ligne('simple', 'verticale', 400, 3000),
                $ligne('double_accole', 'verticale', 600, 3200),
                [...$ligne('double_decale', 'verticale', 600, 4500), 'decalage_joints_min_mm' => 300],
                $ligne('simple', 'horizontale', 600, 2000),
                $ligne('double_decale', 'horizontale', 600, 4000),
            ],
        ]));
    }

    /**
     * @param list<ConfigurationOssature> $configurations
     *
     * @return list<string>
     */
    private static function resume(array $configurations): array
    {
        return array_map(static fn (ConfigurationOssature $c): string => $c->renfort->value.'@'.$c->entraxe, $configurations);
    }

    public function test_mur_plus_haut_que_le_montant_simple_propose_entraxe_reduit_accole_et_decale(): void
    {
        $candidates = (new ConfigurationOssatureResolveur())->candidates($this->profil(), new OrientationPose(Orientation::Verticale), 2900);

        self::assertSame(['simple@400', 'double_accole@600', 'double_decale@600'], self::resume($candidates));
    }

    public function test_hauteur_pour_laquelle_seul_le_renfort_decale_convient(): void
    {
        $candidates = (new ConfigurationOssatureResolveur())->candidates($this->profil(), new OrientationPose(Orientation::Verticale), 3800);

        self::assertSame(['double_decale@600'], self::resume($candidates));
    }

    public function test_un_profile_plus_fort_couvre_la_hauteur_la_ou_le_m48_ne_convient_pas(): void
    {
        $resolveur = new ConfigurationOssatureResolveur();
        $o = new OrientationPose(Orientation::Verticale);
        $m70 = new ProfilView(new ArticleDouble('profil_ossature', [
            'serie' => 70, 'pose.entraxe_defaut' => 600,
            'pose.portees' => [['renfort' => 'simple', 'orientation' => 'verticale', 'entraxe' => 600, 'longueur_max' => 5000]],
        ]));

        $m48 = $resolveur->resoudre($this->profil(), $o, 4800, 4000);
        $plus = $resolveur->resoudre($m70, $o, 4800, 4000);

        self::assertNull($m48->configuration);
        self::assertSame('framing_height_exceeded', $m48->erreur?->code, 'avertissement bloquant : aucun profil M48 ne convient');
        self::assertSame(['height' => 4800, 'profile' => 48], $m48->erreur->params);
        self::assertSame(['simple@600'], self::resume($plus->configuration instanceof ConfigurationOssature ? [$plus->configuration] : []));
    }

    public function test_la_longueur_maximale_depend_de_l_orientation(): void
    {
        $resolveur = new ConfigurationOssatureResolveur();

        $verticale = $resolveur->candidates($this->profil(), new OrientationPose(Orientation::Verticale), 2600);
        $horizontale = $resolveur->candidates($this->profil(), new OrientationPose(Orientation::Horizontale), 2600);

        self::assertSame(['simple@600', 'simple@400', 'double_accole@600', 'double_decale@600'], self::resume($verticale));
        self::assertSame(['double_decale@600'], self::resume($horizontale));
        self::assertSame(4500, $verticale[3]->portee?->longueurMax);
        self::assertSame(4000, $horizontale[0]->portee?->longueurMax);
    }

    public function test_orientation_intermediaire_retient_la_plus_defavorable_avec_avertissement(): void
    {
        $r = (new ConfigurationOssatureResolveur())->resoudre($this->profil(), new OrientationPose(Orientation::Verticale, true), 2300, 3000);

        self::assertSame('simple@400', self::resume([$r->configuration ?? throw new \LogicException()])[0], 'simple@600 exclu : 2000 mm en horizontal < 2300');
        self::assertSame(['framing_orientation_intermediate'], array_map(static fn ($a): string => $a->code, $r->avertissements));
    }

    public function test_strategie_materiel_prend_la_configuration_qui_consomme_le_moins_de_profile(): void
    {
        $r = (new ConfigurationOssatureResolveur())->resoudre($this->profil(), new OrientationPose(Orientation::Verticale), 2900, 4000);

        self::assertSame('simple', $r->configuration?->renfort->value);
        self::assertSame(400, $r->configuration->entraxe);
    }

    public function test_strategie_cout_suit_le_cout_fourni(): void
    {
        $cout = static fn (ConfigurationOssature $c): int => Renfort::DoubleAccole === $c->renfort ? 10 : 1000;

        $r = (new ConfigurationOssatureResolveur())->resoudre($this->profil(), new OrientationPose(Orientation::Verticale), 2900, 4000, strategie: StrategieConfiguration::EconomeCout, cout: $cout);

        self::assertSame(Renfort::DoubleAccole, $r->configuration?->renfort);
    }

    public function test_configuration_imposee_hors_table_acceptee_avec_avertissement(): void
    {
        $imposee = new ConfigurationOssature(48, 300, Renfort::DoubleAccole);
        $dansLaTable = new ConfigurationOssature(48, 600, Renfort::DoubleAccole);
        $resolveur = new ConfigurationOssatureResolveur();
        $o = new OrientationPose(Orientation::Verticale);

        $hors = $resolveur->resoudre($this->profil(), $o, 2900, 4000, strategie: StrategieConfiguration::Imposee, imposee: $imposee);
        $dans = $resolveur->resoudre($this->profil(), $o, 2900, 4000, strategie: StrategieConfiguration::Imposee, imposee: $dansLaTable);

        self::assertSame(300, $hors->configuration?->entraxe);
        self::assertTrue($hors->configuration->estHorsTable());
        self::assertSame(['framing_configuration_off_table'], array_map(static fn ($a): string => $a->code, $hors->avertissements));
        self::assertFalse($dans->configuration?->estHorsTable());
        self::assertSame([], $dans->avertissements);
    }

    public function test_un_montant_non_renforce_trop_long_est_bloquant(): void
    {
        $resolveur = new ConfigurationOssatureResolveur();
        $o = new OrientationPose(Orientation::Verticale);
        $c = new ConfigurationOssature(48, 600, Renfort::DoubleAccole);

        self::assertSame('framing_unreinforced_stud_too_long', $resolveur->verifierNonRenforces($this->profil(), $o, $c, [2400, 2900])?->code);
        self::assertNull($resolveur->verifierNonRenforces($this->profil(), $o, $c, [2400, 2600]));
    }
}
