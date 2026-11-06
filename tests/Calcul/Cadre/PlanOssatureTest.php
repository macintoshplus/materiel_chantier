<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Cadre;

use App\Calcul\Cadre\PlanOssature;
use App\Geometrie\Avertissement;
use App\Tests\Calcul\Support\SurfacesCadre;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlanOssatureTest extends TestCase
{
    use SurfacesCadre;

    public function test_rectangle_rails_sur_toute_la_longueur_et_montants_a_l_entraxe_avec_montant_final(): void
    {
        $plan = PlanOssature::calculer($this->mur(4000, 2500), 600);

        self::assertSame([4000], $plan->railsBas);
        self::assertSame([4000], $plan->railsHaut);
        // 0, 600 ... 3600 (7 montants) puis le montant final à 4000 : un seul montant de rive à chaque bout, pas de doublon.
        self::assertSame(array_fill(0, 8, 2500), $plan->montants);
        self::assertSame([], $plan->raccordements);
        self::assertSame(2500, $plan->hauteurMax);
    }

    public function test_pas_de_montant_final_en_plus_quand_la_longueur_est_un_multiple_de_l_entraxe(): void
    {
        $plan = PlanOssature::calculer($this->mur(3000, 2500), 600);

        self::assertCount(6, $plan->montants, '0, 600, 1200, 1800, 2400, 3000');
    }

    /**
     * @return iterable<string, array{string, string, int, list<string>}>
     */
    public static function extremites(): iterable
    {
        yield 'contre des murs : montants de rive seulement' => ['mur', 'mur', 1, []];
        yield 'contre une cloison : un montant de raccordement' => ['cloison', 'mur', 1, ['raccord']];
        yield 'contre un doublage : un montant de raccordement' => ['mur', 'doublage', 1, ['raccord']];
        yield 'raccordement aux deux bouts' => ['cloison', 'doublage', 1, ['raccord', 'raccord']];
        yield 'deux montants de raccordement paramétrés' => ['cloison', 'mur', 2, ['raccord', 'raccord']];
        yield 'bout libre : avertissement' => ['libre', 'mur', 1, ['libre']];
    }

    /**
     * @param list<string> $attendu
     */
    #[DataProvider('extremites')]
    public function test_extremites(string $debut, string $fin, int $montantsRaccordement, array $attendu): void
    {
        $plan = PlanOssature::calculer($this->mur(4000, 2500), 600, $debut, $fin, $montantsRaccordement);

        $obtenu = array_merge(
            array_fill(0, \count($plan->raccordements), 'raccord'),
            array_map(static fn (Avertissement $a): string => 'framing_free_end' === $a->code ? 'libre' : $a->code, $plan->avertissements),
        );
        self::assertSame($attendu, $obtenu);
        self::assertSame(array_fill(0, 8, 2500), $plan->montants, 'la grille ne change pas : le raccordement est hors grille');
        foreach ($plan->raccordements as $hauteur) {
            self::assertSame(2500, $hauteur);
        }
    }

    public function test_plafond_en_pente_hauteurs_variables_et_rail_haut_en_longueur_reelle(): void
    {
        $plan = PlanOssature::calculer($this->mur(4000, 2000, 2500), 600);

        self::assertSame([2000, 2075, 2150, 2225, 2300, 2375, 2450, 2500], $plan->montants);
        self::assertSame([4000], $plan->railsBas);
        // hypot(4000, 500) = 4031 : longueur de la ligne de jonction, plus longue que la projection horizontale.
        self::assertSame([4031], $plan->railsHaut);
        self::assertSame(2500, $plan->hauteurMax);
    }

    public function test_ouverture_jambages_pieces_au_dessus_et_linteau(): void
    {
        $plan = PlanOssature::calculer($this->mur(4000, 2500, null, [$this->ouverture(1000, 0, 900, 2050)]), 600);

        // Hors ouverture : 0, 600, 2400, 3000, 3600, 4000 (6) ; jambages à 1000 et 1900 (2) ; au-dessus de la porte, sur 1200 et 1800 : 2 x 450.
        $pieces = $plan->montants;
        rsort($pieces);
        self::assertSame([2500, 2500, 2500, 2500, 2500, 2500, 2500, 2500, 450, 450], $pieces);
        self::assertSame([900], $plan->railsOuvertures, 'linteau seul : la porte descend au sol');
    }

    public function test_fenetre_linteau_et_appui(): void
    {
        $plan = PlanOssature::calculer($this->mur(4000, 2500, null, [$this->ouverture(1000, 900, 1200, 1000, 'fenetre')]), 600);

        self::assertSame([1200, 1200], $plan->railsOuvertures);
        self::assertContains(900, $plan->montants, 'sous la fenêtre');
        self::assertContains(600, $plan->montants, 'au-dessus de la fenêtre');
    }

    public function test_positions_de_raccordement_d_un_doublage(): void
    {
        $plan = PlanOssature::calculer($this->mur(4000, 2500), 600, positionsRaccordement: [1500, 9000]);

        self::assertSame([2500], $plan->raccordements, 'la position hors du mur est ignorée');
    }
}
