<?php

declare(strict_types=1);

namespace App\Tests\Geometrie;

use App\Enum\CoteCloison;
use App\Enum\TypeAncrage;
use App\Enum\TypeJonction;
use App\Geometrie\CibleAncrage;
use App\Geometrie\DetecteurAncrages;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\GenerateurFacesCloison;
use App\Geometrie\GenerateurPlafond;
use App\Geometrie\GenerateurSol;
use App\Geometrie\HauteurPlate;
use App\Geometrie\HauteurSousPans;
use App\Geometrie\JonctionsCloison;
use App\Geometrie\OuvertureCloison;
use App\Geometrie\PanPlafond;
use App\Geometrie\Point2D;
use App\Geometrie\Polygone;
use App\Geometrie\Rectangle;
use App\Geometrie\Segment;
use App\Geometrie\TraceCloison;
use PHPUnit\Framework\TestCase;

final class CloisonGeometrieTest extends TestCase
{
    private static function sousPans(): HauteurSousPans
    {
        $sol = (new GenerateurSol())->generer(new Polygone([new Point2D(0, 0), new Point2D(6000, 0), new Point2D(6000, 4000), new Point2D(0, 4000)]));
        $plafond = (new GenerateurPlafond())->doublePente($sol->contour, 2000, 3000);

        return new HauteurSousPans(array_map(
            static fn ($p, $i): PanPlafond => new PanPlafond('pan'.($i + 1), $p->plan, $p->geometrie()->polygone()),
            $plafond->pans,
            array_keys($plafond->pans),
        ));
    }

    public function testTraceLongueurAngleEtNormale(): void
    {
        $t = new TraceCloison(new Point2D(1000, 1000), new Point2D(1000, 4000));

        self::assertSame(3000, $t->longueur());
        self::assertEqualsWithDelta(90.0, $t->angleDegres(), 0.0001);
        self::assertEqualsWithDelta(-1.0, $t->normaleGauche()[0], 0.0001);
        $court = $t->definirLongueur(1500);
        self::assertSame(2500, $court->fin->y);
        $deplace = $t->deplacerExtremite(false, new Point2D(3000, 4000));
        self::assertSame(3000, $deplace->fin->x);
        self::assertSame(1000, $t->fin->x, 'immuable');
    }

    public function testDecalageParRapportAUnMur(): void
    {
        $mur = new Segment(new Point2D(0, 0), new Point2D(6000, 0));
        $t = new TraceCloison(new Point2D(1000, 1500), new Point2D(1000, 3500));

        $decale = $t->decalerParRapportA($mur, 2000);
        // la trace est à gauche (y>0) du mur : distance mesurée au milieu de l'axe
        self::assertSame(2000, $decale->decalageParRapportA($mur));
        self::assertSame(2000, $decale->fin->y - $decale->debut->y);
        $parallele = new TraceCloison(new Point2D(1000, 1500), new Point2D(4000, 1500));
        self::assertSame(2000, $parallele->decalerParRapportA($mur, 2000)->debut->y);
    }

    public function testFacesSousPlafondPlat(): void
    {
        $faces = (new GenerateurFacesCloison())->generer(
            new TraceCloison(new Point2D(1000, 2000), new Point2D(4000, 2000)),
            100,
            new HauteurPlate(2500, 'ceiling'),
        );

        self::assertSame(['db', 'fb', 'fh', 'dh'], $faces->b->contour->ids());
        self::assertSame(['fb', 'db', 'dh', 'fh'], $faces->a->contour->ids());
        self::assertSame(3000 * 2500, $faces->a->geometrie()->polygone()->aire());
        self::assertSame(3000 * 2500, $faces->b->geometrie()->polygone()->aire());
        // B suit le tracé, A est inversé : l'origine de A est à la fin du tracé, côté gauche (y + 50)
        self::assertSame([1000, 1950, 0], $faces->b->plan->origine->versListe());
        self::assertSame([4000, 2050, 0], $faces->a->plan->origine->versListe());
        self::assertSame([3000, 0, 0], $faces->b->plan->u->versListe());
        self::assertSame([-3000, 0, 0], $faces->a->plan->u->versListe());
        // la face A vue de l'intérieur de la pièce : le point u=0 de A est la fin du tracé
        self::assertSame(4000, $faces->a->plan->versPiece(new Point2D(0, 0))->x);
        self::assertSame(1000, $faces->a->plan->versPiece(new Point2D(3000, 0))->x);
    }

    public function testFacesSousPlafondRampant(): void
    {
        // la cloison traverse le faîtage (y = 2000) : le haut de chaque face a une rupture
        $faces = (new GenerateurFacesCloison())->generer(
            new TraceCloison(new Point2D(3000, 500), new Point2D(3000, 3500)),
            100,
            self::sousPans(),
        );

        self::assertSame(['db', 'fb', 'fh', 'r1h', 'dh'], $faces->b->contour->ids());
        $b = $faces->b->contour;
        self::assertEqualsWithDelta(2250, $b->point('dh')->y, 1);
        self::assertEqualsWithDelta(3000, $b->point('r1h')->y, 1);
        self::assertEqualsWithDelta(2250, $b->point('fh')->y, 1);
        self::assertEqualsWithDelta(1500, $b->point('r1h')->x, 1);
        // A est le miroir de B
        $a = $faces->a->contour;
        self::assertSame(['fb', 'db', 'dh', 'r1h', 'fh'], $a->ids());
        self::assertEqualsWithDelta(1500, $a->point('r1h')->x, 1);
        self::assertSame(3000, $a->point('db')->x);
        self::assertSame(0, $a->point('fb')->x);
    }

    public function testHauteurVariableLeLongDuTrace(): void
    {
        // parallèle au faîtage, dans le pan sud : hauteur constante ; perpendiculaire : elle croît
        $faces = (new GenerateurFacesCloison())->generer(new TraceCloison(new Point2D(500, 500), new Point2D(500, 1500)), null, self::sousPans());

        self::assertEqualsWithDelta(2250, $faces->b->contour->point('dh')->y, 1);
        self::assertEqualsWithDelta(2750, $faces->b->contour->point('fh')->y, 1);
        self::assertCount(4, $faces->b->contour->sommets, 'pas de rupture dans un seul pan');
        self::assertSame(GenerateurFacesCloison::EPAISSEUR_PROVISOIRE, $faces->epaisseur);
    }

    public function testCloisonSansPlafondOuDegenereeEstRefusee(): void
    {
        $horsPlafond = new TraceCloison(new Point2D(500, 500), new Point2D(500, 9000));
        try {
            (new GenerateurFacesCloison())->generer($horsPlafond, 100, self::sousPans());
            self::fail();
        } catch (ErreurGeometrique $e) {
            self::assertSame('partition_no_ceiling', $e->erreur);
        }
        $this->expectException(ErreurGeometrique::class);
        (new GenerateurFacesCloison())->generer(new TraceCloison(new Point2D(0, 0), new Point2D(0, 0)), 100, new HauteurPlate(2500));
    }

    public function testDetectionDesAncrages(): void
    {
        $mur = new CibleAncrage(TypeAncrage::Mur, 5, 'mur5', new Segment(new Point2D(0, 0), new Point2D(6000, 0)), 0, 'S1', 'S2');
        $cloison = new CibleAncrage(TypeAncrage::Cloison, 9, 'face9', new Segment(new Point2D(3000, 0), new Point2D(3000, 4000)), 100, 'fb', 'db');
        $detecteur = new DetecteurAncrages();

        $surMur = $detecteur->proposer(new Point2D(1200, 15), [$cloison, $mur]);
        self::assertSame(TypeAncrage::Mur, $surMur->type);
        self::assertSame(1200, $surMur->position);
        self::assertSame(['type' => 'mur', 'cible' => 5, 'position' => 1200], $surMur->versTableau());

        $surCloison = $detecteur->proposer(new Point2D(3060, 2000), [$mur, $cloison]);
        self::assertSame(TypeAncrage::Cloison, $surCloison->type);
        self::assertSame(2000, $surCloison->position, 'distance mesurée à la face (axe - demi-épaisseur)');

        self::assertSame(TypeAncrage::Libre, $detecteur->proposer(new Point2D(1200, 500), [$mur, $cloison])->type);
        self::assertSame(TypeAncrage::Mur, $detecteur->proposer(new Point2D(1200, 500), [$mur], 600)->type, 'tolérance configurable');
        // dans l'angle : le mur est prioritaire à distance égale
        self::assertSame(TypeAncrage::Mur, $detecteur->proposer(new Point2D(3000, 0), [$cloison, $mur])->type);
        self::assertSame(['type' => 'libre', 'cible' => null, 'position' => null], $detecteur->proposer(new Point2D(1, 3000), [])->versTableau());
    }

    public function testJonctionsDeCloison(): void
    {
        $trace = new TraceCloison(new Point2D(0, 2000), new Point2D(3000, 2000));
        $plafond = self::sousPans();
        $faces = (new GenerateurFacesCloison())->generer($trace, 100, $plafond);
        $detecteur = new DetecteurAncrages();
        $mur = new CibleAncrage(TypeAncrage::Mur, 5, 'mur5', new Segment(new Point2D(0, 0), new Point2D(0, 4000)), 0, 'S4', 'S1');
        $debut = $detecteur->proposer($trace->debut, [$mur]);
        $fin = $detecteur->proposer($trace->fin, [$mur]);

        $jonctions = (new JonctionsCloison())->decrire($faces, $trace, $plafond, $debut, $fin);
        $parType = [];
        foreach ($jonctions as $j) {
            $parType[$j->type->value][$j->cote->value] = ($parType[$j->type->value][$j->cote->value] ?? 0) + 1;
        }

        self::assertSame(['A' => 1, 'B' => 1], ($parType['cloison_sur_sol'] ?? []));
        self::assertSame(['A' => 1, 'B' => 1], ($parType['cloison_sous_plafond'] ?? []), 'un seul pan sous la cloison parallèle au faîtage');
        self::assertSame(['A' => 1, 'B' => 1], ($parType['cloison_sur_mur'] ?? []));
        $ancrage = array_values(array_filter($jonctions, static fn ($j): bool => TypeJonction::CloisonSurMur === $j->type && CoteCloison::B === $j->cote))[0];
        self::assertSame(['mur5', 'S4', 'S1', 1950, 2050], [$ancrage->cleB, $ancrage->debutB, $ancrage->finB, $ancrage->debutMm, $ancrage->finMm]);
    }

    public function testCloisonTraversantLeFaitageAUneJonctionParPan(): void
    {
        $trace = new TraceCloison(new Point2D(3000, 500), new Point2D(3000, 3500));
        $plafond = self::sousPans();
        $faces = (new GenerateurFacesCloison())->generer($trace, 100, $plafond);
        $libre = \App\Geometrie\Ancrage::libre();
        $jonctions = (new JonctionsCloison())->decrire($faces, $trace, $plafond, $libre, $libre);

        $pans = array_values(array_filter($jonctions, static fn ($j): bool => TypeJonction::CloisonSousPlafond === $j->type && CoteCloison::B === $j->cote));
        self::assertSame(['pan1', 'pan2'], array_map(static fn ($j): ?string => $j->cleB, $pans));
        self::assertSame([['dh', 'r1h'], ['r1h', 'fh']], array_map(static fn ($j): array => [$j->debutA, $j->finA], $pans));
        self::assertSame([], array_filter($jonctions, static fn ($j): bool => TypeJonction::CloisonSurMur === $j->type));
    }

    public function testOuvertureProjeteeSurLesDeuxFaces(): void
    {
        $dansLeTrace = (new Rectangle(500, 0, 900, 2100))->polygone();
        $p = (new OuvertureCloison())->projeter($dansLeTrace, 3000);

        $b = $p->faceB->boiteEnglobante();
        self::assertSame([500, 1400], [$b->minX, $b->maxX]);
        $a = $p->faceA->boiteEnglobante();
        self::assertSame([1600, 2500], [$a->minX, $a->maxX], 'A est inversé : u depuis la fin');
        self::assertSame(2100, $a->maxY);
        self::assertSame(900 * 2100, $p->faceA->aire());
        self::assertTrue($p->faceA->sensAntiHoraire());
    }
}
