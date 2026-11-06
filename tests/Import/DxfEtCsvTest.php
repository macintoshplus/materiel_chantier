<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Geometrie\ErreurGeometrique;
use App\Import\Csv\ReleveCsvReader;
use App\Import\Dxf\DxfInterpreter;
use App\Import\Dxf\DxfReader;
use App\Import\FormatCsv;
use App\Import\SaisieCotesAngles;
use App\Import\UniteImport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DxfEtCsvTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function dxfAires(): iterable
    {
        yield 'rectangle' => ['rectangle.dxf', 12_000_000];
        yield 'L (POLYLINE/VERTEX)' => ['l.dxf', 14_000_000];
        yield 'pan coupé (LINE)' => ['pan_coupe.dxf', 11_500_000];
        yield 'L en lignes avec jeux de 2 mm' => ['lignes_jeux_2mm.dxf', 14_000_000];
        yield 'mètres via INSUNITS' => ['unites_metres.dxf', 12_000_000];
        yield 'centimètres via INSUNITS' => ['unites_cm.dxf', 12_000_000];
        yield 'pouces via INSUNITS' => ['unites_pouces.dxf', 12_000_000];
        yield 'unité absente (devinée)' => ['unites_absentes_metres.dxf', 12_000_000];
        yield 'CP1252' => ['encodage_cp1252.dxf', 12_000_000];
    }

    #[DataProvider('dxfAires')]
    public function testAireDxf(string $fichier, int $attendue): void
    {
        $plan = Fixtures::dxf($fichier);
        $contours = $plan->contoursSelectionnables();
        self::assertCount(1, $contours, $fichier);
        self::assertEqualsWithDelta($attendue, $contours[0]->polygone()->aire(), $attendue * 0.001, $fichier);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function csvAires(): iterable
    {
        yield 'rectangle xy' => ['rectangle_xy.csv', 12_000_000];
        yield 'L xy' => ['l_xy.csv', 14_000_000];
        yield 'pan coupé xy (virgule)' => ['pan_coupe_xy.csv', 11_500_000];
        yield 'rectangle cotes/angles' => ['rectangle_cotes.csv', 12_000_000];
        yield 'L cotes/angles' => ['l_cotes.csv', 14_000_000];
        yield 'pan coupé cotes/angles' => ['pan_coupe_cotes.csv', 11_500_000];
    }

    #[DataProvider('csvAires')]
    public function testAireCsv(string $fichier, int $attendue): void
    {
        $contours = Fixtures::csv($fichier)->contoursSelectionnables();
        self::assertCount(1, $contours);
        self::assertEqualsWithDelta($attendue, $contours[0]->polygone()->aire(), $attendue * 0.001);
    }

    public function testCsvEnMetres(): void
    {
        $contours = Fixtures::csv('rectangle_metres.csv', UniteImport::Metre)->contoursSelectionnables();
        self::assertSame(12_000_000, $contours[0]->polygone()->aire());
    }

    public function testChainageAvecJeuxDe2Mm(): void
    {
        $plan = Fixtures::dxf('lignes_jeux_2mm.dxf');
        self::assertSame(2, $plan->contours[0]->ecartChainage);
        self::assertSame(6, \count($plan->contours[0]->points));
        $codes = array_map(static fn ($a) => $a->code, $plan->rapport->avertissements);
        self::assertContains('import_chain_gap', $codes);
    }

    public function testJeuAuDelaDeLaToleranceNEstPasChaine(): void
    {
        $plan = Fixtures::dxf('lignes_jeux_8mm.dxf');
        self::assertSame([], $plan->contoursSelectionnables());
        $codes = array_map(static fn ($a) => $a->code, $plan->rapport->avertissements);
        self::assertContains('import_open_chain', $codes);
    }

    public function testArcDansUnContourEstRejete(): void
    {
        $plan = Fixtures::dxf('avec_arc.dxf');
        self::assertCount(1, $plan->contours);
        self::assertSame('import_curved_walls', $plan->contours[0]->rejet?->code);
        self::assertSame([], $plan->contoursSelectionnables());
    }

    public function testBulgeEstRejete(): void
    {
        $plan = Fixtures::dxf('avec_bulge.dxf');
        self::assertSame('import_curved_walls', $plan->contours[0]->rejet?->code);
    }

    public function testCourbesHorsContourIgnoreesAvecAvertissement(): void
    {
        foreach (['arc_isole.dxf' => 'ARC', 'cercle_isole.dxf' => 'CIRCLE'] as $fichier => $type) {
            $plan = Fixtures::dxf($fichier);
            self::assertCount(1, $plan->contoursSelectionnables(), $fichier);
            $trouve = false;
            foreach ($plan->rapport->avertissements as $a) {
                $trouve = $trouve || ('import_curve_ignored' === $a->code && $type === $a->params['type']);
            }
            self::assertTrue($trouve, $fichier);
        }
    }

    public function testUniteCorrigeableSansRelire(): void
    {
        $document = (new DxfReader())->lireFichier(Fixtures::chemin('dxf/unites_absentes_metres.dxf'));
        $interprete = new DxfInterpreter();
        [$unite, $devinee] = $interprete->proposerUnite($document);
        self::assertSame(UniteImport::Metre, $unite);
        self::assertTrue($devinee);
        $faux = $interprete->interpreter($document, UniteImport::Millimetre, true);
        self::assertSame(12, $faux->contoursSelectionnables()[0]->polygone()->aire());
        $bon = $interprete->interpreter($document, UniteImport::Metre);
        self::assertSame(12_000_000, $bon->contoursSelectionnables()[0]->polygone()->aire());
    }

    public function testBlocsPositionnesAvecAttributs(): void
    {
        $plan = Fixtures::dxf('portes_fenetres.dxf');
        self::assertCount(3, $plan->blocs);
        $porte = $plan->blocs[0];
        self::assertSame('PORTE', $porte->nom);
        self::assertSame('A-DOOR', $porte->calque);
        self::assertSame(900, $porte->attributMm(['WIDTH']));
        self::assertSame(2040, $porte->attributMm(['HEIGHT']));
        self::assertSame(900, $porte->largeur);
        self::assertSame('Salon', $plan->textes[0]->texte);
    }

    public function testTexteCp1252(): void
    {
        $plan = Fixtures::dxf('encodage_cp1252.dxf');
        self::assertSame('Séjour é', $plan->textes[0]->texte);
    }

    public function testFichierBinaire(): void
    {
        self::assertSame('import_dxf_binary', Fixtures::codeErreur(static fn () => Fixtures::dxf('binaire.dxf')));
    }

    public function testFichiersCorrompus(): void
    {
        self::assertSame('import_dxf_corrupt', Fixtures::codeErreur(static fn () => Fixtures::dxf('corrompu.dxf')));
        self::assertSame('import_dxf_corrupt', Fixtures::codeErreur(static fn () => Fixtures::dxf('sans_fin.dxf')));
    }

    public function testTailleEtNombreDEntitesLimites(): void
    {
        $contenu = (string) file_get_contents(Fixtures::chemin('dxf/rectangle.dxf'));
        $code = Fixtures::codeErreur(static fn () => (new DxfReader(100))->lireTexte($contenu));
        self::assertSame('import_file_too_large', $code);
        $code = Fixtures::codeErreur(static fn () => (new DxfReader(DxfReader::TAILLE_MAX, 0))->lireTexte($contenu));
        self::assertSame('import_dxf_too_many_entities', $code);
    }

    public function testCsvLignesInvalidesAvecNumeros(): void
    {
        try {
            Fixtures::csv('lignes_invalides.csv');
            self::fail('Exception attendue.');
        } catch (ErreurGeometrique $e) {
            self::assertSame('import_csv_invalid_lines', $e->erreur);
            self::assertSame('4, 5', $e->params['lines']);
        }
    }

    public function testCsvVideEtOuvert(): void
    {
        self::assertSame('import_csv_empty', Fixtures::codeErreur(static fn () => Fixtures::csv('vide.csv')));
        self::assertSame('import_closure_gap', Fixtures::codeErreur(static fn () => Fixtures::csv('ouvert_cotes.csv')));
    }

    public function testCsvFormatForce(): void
    {
        $plan = (new ReleveCsvReader())->lire("4000;90\n3000;90\n4000;90\n3000;90\n", UniteImport::Millimetre, FormatCsv::CotesAngles);
        self::assertSame(12_000_000, $plan->contours[0]->polygone()->aire());
    }

    public function testFermetureAutomatiqueSousSeuil(): void
    {
        $saisie = new SaisieCotesAngles();
        $ferme = $saisie->tracer([4000.0, 3000.0, 4008.0, 3000.0], [90.0, 90.0, 90.0, 90.0]);
        self::assertNotNull($ferme->contour);
        self::assertEqualsWithDelta(8.0, $ferme->ecart, 0.01);
        self::assertSame('import_closure_adjusted', $ferme->avertissement?->code);

        $ouvert = $saisie->tracer([4000.0, 3000.0, 4100.0, 3000.0], [90.0, 90.0, 90.0, 90.0]);
        self::assertNull($ouvert->contour);
        self::assertCount(5, $ouvert->trace);
        self::assertSame('import_closure_gap', $ouvert->avertissement?->code);

        self::assertSame('import_side_invalid', Fixtures::codeErreur(static fn () => $saisie->tracer([4000.0, -1.0, 3000.0], [90.0, 90.0, 90.0])));
    }
}
