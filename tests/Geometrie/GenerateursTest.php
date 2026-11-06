<?php

declare(strict_types=1);

namespace App\Tests\Geometrie;

use App\Enum\TypeJonction;
use App\Geometrie\Direction;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\GenerateurMurs;
use App\Geometrie\GenerateurPlafond;
use App\Geometrie\GenerateurSol;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;
use App\Geometrie\Rectangle;
use App\Geometrie\ServiceJonctions;
use App\Geometrie\SurfaceGeneree;
use App\Geometrie\SurfaceGeometrique;
use PHPUnit\Framework\TestCase;

final class GenerateursTest extends TestCase
{
    /** @param list<array{int, int}> $pts */
    private static function sol(array $pts): SurfaceGeneree
    {
        return (new GenerateurSol())->generer(new Polygone(array_map(static fn (array $p): Point2D => new Point2D($p[0], $p[1]), $pts)));
    }

    /**
     * @param list<SurfaceGeneree> $surfaces
     *
     * @return array<string, SurfaceGeometrique>
     */
    private static function cles(string $prefixe, array $surfaces): array
    {
        $r = [];
        foreach ($surfaces as $i => $s) {
            $r[$prefixe.($i + 1)] = $s->geometrie();
        }

        return $r;
    }

    public function testFormeEnLDonneSixMursEtUnAngleRentrant(): void
    {
        $sol = self::sol([[0, 0], [4000, 0], [4000, 2000], [2000, 2000], [2000, 3000], [0, 3000]]);
        $murs = (new GenerateurMurs())->generer($sol->contour, 2500);
        $plafond = (new GenerateurPlafond())->plat($sol->contour, 2500);

        self::assertCount(6, $murs);
        self::assertSame(['S1', 'S2', 'S3', 'S4', 'S5', 'S6'], $sol->contour->ids());
        $jonctions = (new ServiceJonctions())->detecter(['sol' => $sol->geometrie(), ...self::cles('m', $murs), ...self::cles('p', $plafond)]);

        $murMur = array_values(array_filter($jonctions, static fn ($j): bool => str_starts_with($j->cleA, 'm') && str_starts_with($j->cleB, 'm')));
        self::assertCount(6, $murMur);
        $rentrants = array_values(array_filter($murMur, static fn ($j): bool => TypeJonction::AngleRentrant === $j->type));
        self::assertCount(1, $rentrants);
        self::assertSame(['m3', 'm4'], [$rentrants[0]->cleA, $rentrants[0]->cleB]);
        self::assertSame(5, \count(array_filter($murMur, static fn ($j): bool => TypeJonction::AngleSaillant === $j->type)));
        // sol (6) + plafond (6) + murs (6) : 18 jonctions, toutes cohérentes
        self::assertCount(18, $jonctions);
        self::assertSame([], (new ServiceJonctions())->verifier($jonctions));
    }

    public function testMurDeLaPieceRectangulaire(): void
    {
        $sol = self::sol([[0, 0], [4000, 0], [4000, 3000], [0, 3000]]);
        $murs = (new GenerateurMurs())->generer($sol->contour, 2500);

        self::assertSame(['S2', 'S1', 'S1h', 'S2h'], $murs[0]->contour->ids());
        self::assertSame(4000, $murs[0]->geometrie()->polygone()->boiteEnglobante()->largeur());
        self::assertSame(3000, $murs[1]->geometrie()->polygone()->boiteEnglobante()->largeur());
        self::assertSame(['debut' => 'S1', 'fin' => 'S2'], $murs[0]->areteSol);
        // Mur 1 sur le côté sud, vu de l'intérieur : l'origine est au sommet de fin
        self::assertSame([4000, 0, 0], $murs[0]->plan->origine->versListe());
        self::assertSame(4000, $murs[0]->geometrie()->segmentAuSol()->longueur());
    }

    public function testHauteurInvalideEtSolNonSimple(): void
    {
        $sol = self::sol([[0, 0], [4000, 0], [4000, 3000], [0, 3000]]);
        $this->expectException(ErreurGeometrique::class);
        (new GenerateurMurs())->generer($sol->contour, 0);
    }

    public function testSolNonSimpleRefuse(): void
    {
        $this->expectException(ErreurGeometrique::class);
        self::sol([[0, 0], [4000, 3000], [4000, 0], [0, 3000]]);
    }

    public function testDoublePenteDonneLaLongueurReelleDesFourrures(): void
    {
        $sol = self::sol([[0, 0], [6000, 0], [6000, 4000], [0, 4000]]);
        $plafond = (new GenerateurPlafond())->doublePente($sol->contour, 2000, 3000);

        self::assertCount(2, $plafond->pans);
        self::assertCount(2, $plafond->pignons);
        $attendue = (int) round(hypot(2000, 1000));
        foreach ($plafond->pans as $pan) {
            $coupes = $pan->geometrie()->coupes(Direction::Verticale, 600);
            self::assertNotEmpty($coupes);
            foreach ($coupes as $coupe) {
                self::assertEqualsWithDelta($attendue, $coupe->longueur(), 1);
            }
            self::assertEqualsWithDelta(26.565, $pan->plan->anglePenteDegres(), 0.01);
            self::assertEqualsWithDelta($attendue, $pan->plan->longueurReelle(new \App\Geometrie\Segment(new Point2D(0, 0), new Point2D(0, $attendue))), 1);
        }
        // la cote du pan à l'égout et au faîtage
        self::assertSame(2000, $plafond->pans[0]->plan->coteAu(new Point2D(3000, 0)));
        self::assertEqualsWithDelta(3000, $plafond->pans[0]->plan->coteAu(new Point2D(3000, 2000)), 1);
    }

    public function testPignonPentagonalDonneDesCoupesDeLongueursDifferentes(): void
    {
        $sol = self::sol([[0, 0], [6000, 0], [6000, 4000], [0, 4000]]);
        $plafond = (new GenerateurPlafond())->doublePente($sol->contour, 2000, 3000);
        $pignon = $plafond->pignons[0]->geometrie();

        self::assertCount(5, $plafond->pignons[0]->contour->sommets);
        self::assertSame(10_000_000, $pignon->polygone()->aire());
        $longueurs = array_map(static fn ($c): int => $c->longueur(), $pignon->coupes(Direction::Verticale, 500));
        // 0 -> 4000 tous les 500 : 2000, 2250, 2500, 2750, 3000, 2750, 2500, 2250, 2000
        self::assertSame([2000, 2250, 2500, 2750, 3000, 2750, 2500, 2250, 2000], $longueurs);
        self::assertGreaterThan(1, \count(array_unique($longueurs)));
    }

    public function testDoublePenteSurLAxeY(): void
    {
        $sol = self::sol([[0, 0], [3000, 0], [3000, 8000], [0, 8000]]);
        $plafond = (new GenerateurPlafond())->doublePente($sol->contour, 2000, 2600);
        $jonctions = (new ServiceJonctions())->detecter([
            ...self::cles('m', (new GenerateurMurs())->generer($sol->contour, 2000)),
            ...self::cles('p', $plafond->pans),
            ...self::cles('g', $plafond->pignons),
        ]);

        $faitage = array_values(array_filter($jonctions, static fn ($j): bool => TypeJonction::Faitage === $j->type));
        self::assertCount(1, $faitage);
        self::assertEqualsWithDelta(8000, $faitage[0]->longueurA, 1);
        foreach ($plafond->pans as $pan) {
            // course 1500 pour 600 de montée
            self::assertEqualsWithDelta(hypot(1500, 600), $pan->geometrie()->polygone()->boiteEnglobante()->hauteur(), 1);
        }
    }

    public function testJonctionsDuPlafondADoublePente(): void
    {
        $sol = self::sol([[0, 0], [6000, 0], [6000, 4000], [0, 4000]]);
        $plafond = (new GenerateurPlafond())->doublePente($sol->contour, 2000, 3000);
        $murs = (new GenerateurMurs())->generer($sol->contour, 2000);
        // les murs perpendiculaires au faîtage sont remplacés par les pignons
        $surfaces = ['sol' => $sol->geometrie()];
        foreach ($murs as $i => $mur) {
            $remplace = array_values(array_filter($plafond->pignons, static fn ($g): bool => $g->areteSol === $mur->areteSol));
            $surfaces['m'.($i + 1)] = ([] !== $remplace ? $remplace[0] : $mur)->geometrie();
        }
        foreach ($plafond->pans as $i => $pan) {
            $surfaces['p'.($i + 1)] = $pan->geometrie();
        }
        $service = new ServiceJonctions();
        $jonctions = $service->detecter($surfaces);

        $par = [];
        foreach ($jonctions as $j) {
            $par[$j->type->value] = ($par[$j->type->value] ?? 0) + 1;
        }
        self::assertSame(1, ($par['faitage'] ?? 0));
        self::assertSame(2, ($par['sabliere'] ?? 0));
        self::assertSame([], $service->verifier($jonctions), 'longueurs égales aux jonctions, pentes comprises');
    }

    public function testPlafondRefuseSolNonRectangulaireOuHauteursInvalides(): void
    {
        $l = self::sol([[0, 0], [4000, 0], [4000, 2000], [2000, 2000], [2000, 3000], [0, 3000]]);
        try {
            (new GenerateurPlafond())->doublePente($l->contour, 2000, 3000);
            self::fail();
        } catch (ErreurGeometrique $e) {
            self::assertSame('ceiling_unsupported_floor', $e->erreur);
        }
        $r = self::sol([[0, 0], [4000, 0], [4000, 3000], [0, 3000]]);
        $this->expectException(ErreurGeometrique::class);
        (new GenerateurPlafond())->doublePente($r->contour, 3000, 2000);
    }

    public function testEgaliteDesLongueursSaufJonctionsDeCloison(): void
    {
        self::assertTrue(TypeJonction::AngleSaillant->exigeEgaliteLongueurs());
        self::assertTrue(TypeJonction::Faitage->exigeEgaliteLongueurs());
        foreach ([TypeJonction::CloisonSurMur, TypeJonction::CloisonSurCloison, TypeJonction::CloisonSurDoublage, TypeJonction::CloisonSurSol, TypeJonction::CloisonSousPlafond] as $type) {
            self::assertFalse($type->exigeEgaliteLongueurs());
        }
        // deux surfaces qui partagent des sommets mais dont les arêtes diffèrent de plus d'1 mm
        $a = new SurfaceGeometrique(
            new \App\Geometrie\Plan(new \App\Geometrie\Point3D(0, 0, 0), new \App\Geometrie\Point3D(1000, 0, 0), new \App\Geometrie\Point3D(0, 0, 1000)),
            new \App\Geometrie\ContourIdentifie([
                new \App\Geometrie\Sommet('p', new Point2D(0, 0)), new \App\Geometrie\Sommet('q', new Point2D(1000, 0)), new \App\Geometrie\Sommet('r', new Point2D(0, 1000)),
            ]),
        );
        $b = new SurfaceGeometrique(
            new \App\Geometrie\Plan(new \App\Geometrie\Point3D(0, 0, 0), new \App\Geometrie\Point3D(0, 1000, 0), new \App\Geometrie\Point3D(0, 0, 1000)),
            new \App\Geometrie\ContourIdentifie([
                new \App\Geometrie\Sommet('q', new Point2D(1200, 0)), new \App\Geometrie\Sommet('p', new Point2D(0, 0)), new \App\Geometrie\Sommet('s', new Point2D(0, 1000)),
            ]),
        );
        $service = new ServiceJonctions();
        $avertissements = $service->verifier($service->detecter(['a' => $a, 'b' => $b]));

        self::assertCount(1, $avertissements);
        self::assertSame('junction_length_mismatch', $avertissements[0]->code);
        self::assertSame(1000, $avertissements[0]->params['lengthA']);
        self::assertSame(1200, $avertissements[0]->params['lengthB']);
    }

    public function testRectangleDeReference(): void
    {
        self::assertSame(2_000_000, (new Rectangle(0, 0, 2000, 1000))->polygone()->aire());
    }
}
