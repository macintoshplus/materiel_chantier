<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Electricite;

use App\Calcul\Electricite\CheminementCalculator;
use App\Calcul\Electricite\ElementReseau;
use App\Calcul\Electricite\GaineDisponible;
use App\Calcul\Electricite\Liaison;
use App\Calcul\Electricite\PointCheminement;
use App\Calcul\Electricite\RegistreTypesElements;
use App\Calcul\Electricite\ReseauCalcul;
use App\Calcul\Electricite\Section;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheminementCalculatorTest extends TestCase
{
    private RegistreTypesElements $types;

    protected function setUp(): void
    {
        $this->types = new RegistreTypesElements([
            'tableau' => ['libelle' => 'Board', 'famille' => 'distribution'],
            'interrupteur' => ['libelle' => 'Switch', 'famille' => 'commande'],
            'va_et_vient' => ['libelle' => 'Two-way', 'famille' => 'commande', 'navette' => true],
            'point_lumineux' => ['libelle' => 'Light', 'famille' => 'luminaire'],
            'prise' => ['libelle' => 'Socket', 'famille' => 'prise'],
        ]);
    }

    private function element(string $cle, string $type, string $piece, int $x, int $y, int $z, int $plafond = 2500): ElementReseau
    {
        return new ElementReseau($cle, $cle, $this->types->get($type), new PointCheminement($piece, $x, $y, $z, $plafond));
    }

    /**
     * @return list<GaineDisponible>
     */
    private static function gaines(): array
    {
        return [
            new GaineDisponible(20, 40, [150 => 8, 250 => 5]),
            new GaineDisponible(16, 40, [150 => 5, 250 => 3]),
            new GaineDisponible(25, 40, [150 => 14, 250 => 9]),
        ];
    }

    /**
     * Pièce 4000 x 3000, plafond à 2500 : tableau (0, 1000, 1800), interrupteur (0, 2500, 1100), point lumineux au plafond (2000, 1500, 2500).
     * Tableau -> interrupteur : 1500 (axe y) + 700 (montée) + 1400 (descente) = 3600 mm.
     * Interrupteur -> point lumineux : 2000 (x) + 1000 (y) + 1400 (montée) = 4400 mm.
     */
    public function testPieceAvecTableauInterrupteurEtPointLumineux(): void
    {
        $elements = [
            'T' => $this->element('T', 'tableau', 'A', 0, 1000, 1800),
            'S' => $this->element('S', 'interrupteur', 'A', 0, 2500, 1100),
            'L' => $this->element('L', 'point_lumineux', 'A', 2000, 1500, 2500),
        ];
        $reseau = new ReseauCalcul('Lighting', 'T', $elements, [new Liaison('S', 'T'), new Liaison('L', 'S')], 150);

        $resultat = (new CheminementCalculator())->calculer($reseau, self::gaines());

        self::assertSame([], $resultat->erreurs);
        self::assertSame([], $resultat->avertissements);
        self::assertSame([3600, 4400], array_map(static fn ($t) => $t->longueurMm, $resultat->troncons));
        // Gaine : 3600 + 4400 en diamètre 16 (3 conducteurs <= 5).
        self::assertSame([16 => 8000], $resultat->gaines);
        // Câble : 3600 x 1,10 = 3960 + 400 ; 4400 x 1,10 = 4840 + 400 ; 3 conducteurs (2 actifs + terre).
        self::assertSame([['section' => 150, 'conducteurs' => 3, 'longueur' => 4360 + 5240]], $resultat->cables);
        self::assertSame(9600, $resultat->longueurCableTotale());
        self::assertSame(8000, $resultat->longueurGaineTotale());
    }

    public function testVaEtVientAtroisConducteursPlusTerreEntreLesCommandes(): void
    {
        $elements = [
            'T' => $this->element('T', 'tableau', 'A', 0, 1000, 1800),
            'L' => $this->element('L', 'point_lumineux', 'A', 2000, 1500, 2500),
            'V1' => $this->element('V1', 'va_et_vient', 'A', 0, 2500, 1100),
            'V2' => $this->element('V2', 'va_et_vient', 'A', 3000, 100, 1100),
        ];
        $reseau = new ReseauCalcul('Lighting', 'T', $elements, [new Liaison('L', 'T'), new Liaison('V1', 'L'), new Liaison('V2', 'V1')], 150);

        $resultat = (new CheminementCalculator())->calculer($reseau, self::gaines());

        self::assertSame([], $resultat->erreurs);
        $parEnfant = [];
        foreach ($resultat->troncons as $troncon) {
            $parEnfant[$troncon->enfant] = $troncon;
        }
        // Entre les deux commandes : 3 actifs + terre.
        self::assertSame(3, $parEnfant['V2']->conducteursActifs);
        self::assertTrue($parEnfant['V2']->terre);
        self::assertSame(4, $parEnfant['V2']->conducteurs());
        // Les autres tronçons restent à 2 actifs + terre.
        self::assertSame(3, $parEnfant['L']->conducteurs());
        self::assertSame(3, $parEnfant['V1']->conducteurs());
        // Le câble à 4 conducteurs est compté à part du câble à 3 conducteurs.
        $conducteurs = array_column($resultat->cables, 'conducteurs');
        self::assertSame([3, 4], $conducteurs);
    }

    public function testLiaisonSurchargeLeNombreDeConducteurs(): void
    {
        $elements = [
            'T' => $this->element('T', 'tableau', 'A', 0, 0, 1800),
            'P' => $this->element('P', 'prise', 'A', 1000, 0, 300),
        ];
        $reseau = new ReseauCalcul('R', 'T', $elements, [new Liaison('P', 'T', 4)], 250);

        $resultat = (new CheminementCalculator())->calculer($reseau, self::gaines());

        self::assertSame(5, $resultat->troncons[0]->conducteurs());
        self::assertSame([20 => 3900], $resultat->gaines);
    }

    public function testReserveParametrable(): void
    {
        $elements = [
            'T' => $this->element('T', 'tableau', 'A', 0, 0, 2500),
            'P' => $this->element('P', 'prise', 'A', 1000, 0, 2500),
        ];
        $liaisons = [new Liaison('P', 'T')];
        $sans = (new CheminementCalculator())->calculer(new ReseauCalcul('R', 'T', $elements, $liaisons, 150, 0), self::gaines());
        $avec = (new CheminementCalculator())->calculer(new ReseauCalcul('R', 'T', $elements, $liaisons, 150, 25), self::gaines());

        // 1000 mm : câble = 1000 + 2 x 200 sans réserve, 1250 + 400 avec 25 %.
        self::assertSame(1400, $sans->longueurCableTotale());
        self::assertSame(1650, $avec->longueurCableTotale());
        self::assertSame(1000, $avec->longueurGaineTotale());
    }

    /**
     * @return iterable<string, array{int, int, ?int}>
     */
    public static function choixDuDiametre(): iterable
    {
        yield '3 conducteurs : le plus petit' => [150, 3, 16];
        yield '5 conducteurs : limite du 16' => [150, 5, 16];
        yield '6 conducteurs : le 20' => [150, 6, 20];
        yield '9 conducteurs : le 25' => [150, 9, 25];
        yield '14 conducteurs : limite du 25' => [150, 14, 25];
        yield '15 conducteurs : aucun' => [150, 15, null];
        yield 'section absente du catalogue' => [600, 3, null];
    }

    #[DataProvider('choixDuDiametre')]
    public function testChoixDuDiametreDeGaine(int $section, int $conducteurs, ?int $diametre): void
    {
        $elements = [
            'T' => $this->element('T', 'tableau', 'A', 0, 0, 2500),
            'P' => $this->element('P', 'prise', 'A', 1000, 0, 2500),
        ];
        // conducteurs = actifs + terre
        $reseau = new ReseauCalcul('R', 'T', $elements, [new Liaison('P', 'T', $conducteurs - 1)], $section);

        $resultat = (new CheminementCalculator())->calculer($reseau, self::gaines());

        if (null === $diametre) {
            self::assertCount(1, $resultat->erreurs);
            self::assertSame('electrical_no_conduit', $resultat->erreurs[0]->code);
            self::assertSame([], $resultat->gaines);

            return;
        }
        self::assertSame([], $resultat->erreurs);
        self::assertSame([$diametre => 1000], $resultat->gaines);
    }

    public function testCatalogueSansGaineEstUneErreurBloquante(): void
    {
        $elements = [
            'T' => $this->element('T', 'tableau', 'A', 0, 0, 2500),
            'P' => $this->element('P', 'prise', 'A', 1000, 0, 2500),
        ];

        $resultat = (new CheminementCalculator())->calculer(new ReseauCalcul('R', 'T', $elements, [new Liaison('P', 'T')], 150), []);

        self::assertSame('electrical_no_conduit', $resultat->erreurs[0]->code);
    }

    public function testAutrePieceParLaPorteLaPlusProche(): void
    {
        $elements = [
            'T' => $this->element('T', 'tableau', 'A', 0, 0, 1800),
            'P' => $this->element('P', 'prise', 'B', 500, 500, 300),
        ];
        $passages = [
            'A' => [new PointCheminement('A', 3000, 0, 2040, 2500), new PointCheminement('A', 0, 1000, 2040, 2500)],
            'B' => [new PointCheminement('B', 0, 500, 2040, 2500)],
        ];
        $calculateur = new CheminementCalculator();

        // Pièce A : tableau -> porte (0, 1000) : 1000 + 700 (montée) + 460 (descente) = 2160.
        // Pièce B : porte (0, 500) -> prise (500, 500) : 500 + 460 + 2200 = 3160.
        self::assertSame(2160 + 3160, $calculateur->distance($elements['T']->point, $elements['P']->point, $passages));

        $resultat = $calculateur->calculer(new ReseauCalcul('R', 'T', $elements, [new Liaison('P', 'T')], 150), self::gaines(), $passages);
        self::assertSame([], $resultat->erreurs);
        self::assertSame(5320, $resultat->troncons[0]->longueurMm);
    }

    public function testAutrePieceSansPorteEstSignale(): void
    {
        $elements = [
            'T' => $this->element('T', 'tableau', 'A', 0, 0, 1800),
            'P' => $this->element('P', 'prise', 'B', 500, 500, 300),
        ];

        $resultat = (new CheminementCalculator())->calculer(new ReseauCalcul('R', 'T', $elements, [new Liaison('P', 'T')], 150), self::gaines());

        self::assertSame([], $resultat->troncons);
        self::assertSame('electrical_no_door', $resultat->avertissements[0]->code);
    }

    public function testElementNonRelieEtDepartAbsent(): void
    {
        $elements = [
            'T' => $this->element('T', 'tableau', 'A', 0, 0, 1800),
            'P' => $this->element('P', 'prise', 'A', 500, 0, 300),
            'Q' => $this->element('Q', 'prise', 'A', 900, 0, 300),
        ];
        $calculateur = new CheminementCalculator();

        $resultat = $calculateur->calculer(new ReseauCalcul('R', 'T', $elements, [new Liaison('P', 'T'), new Liaison('Q', 'X')], 150), self::gaines());
        self::assertCount(1, $resultat->troncons);
        self::assertSame('electrical_unreachable', $resultat->avertissements[0]->code);

        $sansDepart = $calculateur->calculer(new ReseauCalcul('R', 'Z', $elements, [new Liaison('P', 'T')], 150), self::gaines());
        self::assertSame('electrical_start_missing', $sansDepart->erreurs[0]->code);
    }

    public function testSection(): void
    {
        self::assertSame(150, Section::centiemes('1.5'));
        self::assertSame(250, Section::centiemes('2.50'));
        self::assertSame(600, Section::centiemes('6'));
        self::assertSame(100, Section::centiemes(1));
        self::assertSame('1.5', Section::texte(150));
        self::assertSame('6', Section::texte(600));
        self::assertSame('0.75', Section::texte(75));
        $this->expectException(\InvalidArgumentException::class);
        Section::centiemes('abc');
    }
}
