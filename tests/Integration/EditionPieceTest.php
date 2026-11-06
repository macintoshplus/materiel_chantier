<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Cloison;
use App\Entity\Jonction;
use App\Entity\Ouverture;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Enum\TypeJonction;
use App\Geometrie\Avertissement;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\Point2D;
use App\Geometrie\Segment;
use App\Geometrie\TraceCloison;
use App\Service\CloisonEditionService;
use App\Service\PieceCreationService;
use App\Service\PieceEditionService;
use App\Service\PieceJonctions;
use App\Service\SuppressionCloison;
use App\Tests\Support\BaseDeDonneesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EditionPieceTest extends KernelTestCase
{
    use BaseDeDonneesTrait;

    private EntityManagerInterface $em;

    /**
     * @param list<array{x: int, y: int}> $forme
     */
    private function piece(array $forme, ?int $faitage = null): Piece
    {
        self::bootKernel();
        $this->em = $this->creerSchema();
        $projet = new Projet();
        $projet->setNom('P');
        $piece = new Piece($projet);
        $piece->setNom('Salon');
        $piece->setFormeSol($forme);
        $piece->setHauteurSousPlafond(2500);
        (new PieceCreationService(new PieceJonctions()))->generer($piece, $faitage);
        $this->em->persist($projet);
        $this->em->flush();

        return $piece;
    }

    private function edition(): PieceEditionService
    {
        $jonctions = new PieceJonctions();

        return new PieceEditionService($this->em, $jonctions, new CloisonEditionService($this->em, $jonctions));
    }

    private function rectangle(): Piece
    {
        return $this->piece([['x' => 0, 'y' => 0], ['x' => 4000, 'y' => 0], ['x' => 4000, 'y' => 3000], ['x' => 0, 'y' => 3000]]);
    }

    /**
     * @param list<Avertissement> $impacts
     *
     * @return list<string>
     */
    private static function codes(array $impacts): array
    {
        return array_map(static fn (Avertissement $a): string => $a->code, $impacts);
    }

    public function testCreationRectangle(): void
    {
        $piece = $this->rectangle();
        self::assertNotNull($piece->getSol());
        self::assertCount(4, $piece->getMurs());
        self::assertCount(1, $piece->getPans());
        $this->em->clear();
        $relue = $this->em->getRepository(Piece::class)->find($piece->getId());
        self::assertNotNull($relue);
        // 4 jonctions sol/mur + 4 mur/mur + 4 mur/plafond
        self::assertCount(12, $relue->getJonctions());
        self::assertSame(['S1', 'S2', 'S3', 'S4'], $relue->getSol()?->getContour()->ids());
    }

    public function testCreationDoublePente(): void
    {
        $piece = $this->piece([['x' => 0, 'y' => 0], ['x' => 4000, 'y' => 0], ['x' => 4000, 'y' => 3000], ['x' => 0, 'y' => 3000]], 3500);
        self::assertCount(2, $piece->getPans());
        $types = array_map(static fn (Jonction $j): TypeJonction => $j->getType(), $piece->getJonctions()->toArray());
        self::assertContains(TypeJonction::Faitage, $types);
    }

    public function testCreationRefuseHauteurAbsente(): void
    {
        self::bootKernel();
        $projet = new Projet();
        $projet->setNom('P');
        $piece = new Piece($projet);
        $piece->setFormeSol([['x' => 0, 'y' => 0], ['x' => 4000, 'y' => 0], ['x' => 4000, 'y' => 3000]]);
        $this->expectException(ErreurGeometrique::class);
        (new PieceCreationService(new PieceJonctions()))->generer($piece);
    }

    public function testDeplacementMetAJourLesDeuxMursVoisins(): void
    {
        $piece = $this->rectangle();
        $ids = array_map(static fn ($m) => $m->getId(), $piece->getMurs());
        $plan = $this->edition()->appliquerDeplacement($piece, 'S3', new Point2D(4500, 3000));
        self::assertContains('ceiling_not_updated', self::codes($plan->impacts));
        self::assertSame(['x' => 4500, 'y' => 3000], $piece->getFormeSol()[2]);
        self::assertSame($ids, array_map(static fn ($m) => $m->getId(), $piece->getMurs()), 'les murs gardent leur identité');
        $mur = $piece->getMurs()[1]; // S2 -> S3 : devient oblique
        self::assertSame(['S2', 'S3'], array_values($mur->getAreteSol() ?? []));
        self::assertSame((int) round(hypot(500, 3000)), $mur->getContour()->point('S2')->x);
    }

    public function testDeplacementRefuseUnContourQuiSeCroise(): void
    {
        $piece = $this->rectangle();
        $this->expectException(ErreurGeometrique::class);
        $this->edition()->planifierDeplacement($piece, 'S3', new Point2D(-1000, -1000));
    }

    public function testScissionConserveNomEtOuvertures(): void
    {
        $piece = $this->rectangle();
        $mur = $piece->getMurs()[0]; // S1 -> S2, 4000 de large, origine en S2
        $mur->setNom('Façade');
        $a = new Ouverture($mur);
        $a->setNom('Porte');
        $a->definirRectangle(500, 0, 900, 2100); // côté S2 (u petit)
        $b = new Ouverture($mur);
        $b->setNom('Fenêtre');
        $b->definirRectangle(3000, 900, 800, 1000); // côté S1
        $c = new Ouverture($mur);
        $c->setNom('Baie');
        $c->definirRectangle(2200, 0, 600, 2000); // à cheval sur le point de coupe (u = 2500)
        $this->em->flush();

        $plan = $this->edition()->planifierScission($piece, 'S1', 1500);
        self::assertContains('opening_straddles_split', self::codes($plan->impacts));
        $avant = \count($piece->getMurs());
        self::assertSame(4, $avant, 'planifier ne modifie rien');

        $this->edition()->appliquerScission($piece, 'S1', 1500);
        self::assertSame($avant + 1, \count($piece->getMurs()));
        self::assertSame(['S1', 'S5'], array_values($mur->getAreteSol() ?? []));
        self::assertSame('Façade (1)', $mur->getNom());
        $nouveau = $piece->getMurs()[4];
        self::assertSame('Façade (2)', $nouveau->getNom());
        self::assertSame(['S5', 'S2'], array_values($nouveau->getAreteSol() ?? []));
        self::assertSame(1500, $mur->getContour()->point('S1')->x);
        self::assertSame(2500, $nouveau->getContour()->point('S5')->x);
        self::assertSame(['Fenêtre'], array_values(array_map(static fn (Ouverture $o): ?string => $o->getNom(), $mur->getOuvertures()->toArray())));
        self::assertSame([500], array_values(array_map(static fn (Ouverture $o): int => $o->getU(), $mur->getOuvertures()->toArray())), 'la fenêtre (u 3000) est décalée de la largeur du nouveau mur (2500)');
        $noms = array_map(static fn (Ouverture $o): ?string => $o->getNom(), $nouveau->getOuvertures()->toArray());
        sort($noms);
        self::assertSame(['Baie', 'Porte'], $noms);
        self::assertCount(3, $this->em->getRepository(Ouverture::class)->findAll(), 'aucune ouverture supprimée');
    }

    public function testScissionPuisFusionRestituentLeMur(): void
    {
        $piece = $this->rectangle();
        $mur = $piece->getMurs()[0];
        $mur->setNom('Façade');
        $o = new Ouverture($mur);
        $o->setNom('Porte');
        $o->definirRectangle(500, 0, 900, 2100);
        $p = new Ouverture($mur);
        $p->setNom('Fenêtre');
        $p->definirRectangle(3000, 900, 800, 1000);
        $this->em->flush();

        $edition = $this->edition();
        $edition->appliquerScission($piece, 'S1', 1500);
        $plan = $edition->planifierFusion($piece, 'S5');
        self::assertTrue($plan->confirmationRequise, 'les noms (1) et (2) diffèrent');
        self::assertContains('merge_names_differ', self::codes($plan->impacts));
        $edition->appliquerFusion($piece, 'S5');

        self::assertCount(4, $piece->getMurs());
        self::assertSame('Façade (1)', $mur->getNom(), 'le premier mur garde son nom');
        self::assertSame(['S1', 'S2'], array_values($mur->getAreteSol() ?? []));
        $positions = [];
        foreach ($mur->getOuvertures() as $ouverture) {
            $positions[$ouverture->getNom() ?? ''] = $ouverture->getU();
        }
        ksort($positions);
        self::assertSame(['Fenêtre' => 3000, 'Porte' => 500], $positions, 'ouvertures revenues à leur position initiale');
        self::assertSame(4000, $mur->getContour()->point('S1')->x);
    }

    public function testFusionRefuseSousTroisSommets(): void
    {
        $piece = $this->piece([['x' => 0, 'y' => 0], ['x' => 4000, 'y' => 0], ['x' => 0, 'y' => 3000]]);
        $this->expectException(ErreurGeometrique::class);
        $this->edition()->planifierFusion($piece, 'S2');
    }

    public function testScissionRefuseUnePositionHorsArete(): void
    {
        $piece = $this->rectangle();
        $this->expectException(ErreurGeometrique::class);
        $this->edition()->planifierScission($piece, 'S1', 4000);
    }

    public function testOuvertureQuiSortDuMurEstSignaleeEtConservee(): void
    {
        $piece = $this->rectangle();
        $mur = $piece->getMurs()[0];
        $o = new Ouverture($mur);
        $o->setNom('Baie');
        $o->definirRectangle(3000, 0, 900, 2100);
        $this->em->flush();

        $plan = $this->edition()->appliquerDeplacement($piece, 'S1', new Point2D(1500, 0));
        self::assertContains('opening_outside_surface', self::codes($plan->impacts));
        self::assertCount(1, $mur->getOuvertures());
        self::assertCount(1, $this->em->getRepository(Ouverture::class)->findAll());
    }

    public function testCloisonCreeSesFacesEtSesJonctions(): void
    {
        $piece = $this->rectangle();
        $cloison = new Cloison($piece);
        $cloison->setNom('Cloison');
        $cloison->setEpaisseur(100);
        $this->em->persist($cloison);
        $cloison->definirTrace(new TraceCloison(new Point2D(0, 1500), new Point2D(2000, 1500)));
        $service = new CloisonEditionService($this->em, new PieceJonctions());
        $plan = $service->regenerer($cloison);

        self::assertTrue($plan->estVide(), 'extrémités ancrées sur des murs, aucune alerte');
        self::assertCount(2, $cloison->getFaces());
        self::assertSame('mur', $cloison->getAncrageDebut()['type']);
        self::assertSame('libre', $cloison->getAncrageFin()['type']);
        $types = [];
        foreach ($piece->getJonctions() as $j) {
            if ($j->getSurfaceA()->getCloison() === $cloison) {
                $types[] = $j->getType()->value;
            }
        }
        sort($types);
        self::assertSame(['cloison_sous_plafond', 'cloison_sous_plafond', 'cloison_sur_mur', 'cloison_sur_mur', 'cloison_sur_sol', 'cloison_sur_sol'], $types);
    }

    public function testCloisonAngleEtDecalage(): void
    {
        $piece = $this->rectangle();
        $cloison = new Cloison($piece);
        $cloison->setNom('Cloison');
        $cloison->setEpaisseur(100);
        $this->em->persist($cloison);
        $cloison->definirTrace(new TraceCloison(new Point2D(500, 1500), new Point2D(1500, 1500)));
        $service = new CloisonEditionService($this->em, new PieceJonctions());
        $service->regenerer($cloison);

        $service->definirAngle($cloison, 90.0);
        self::assertSame(1000, $cloison->getTrace()->longueur());
        self::assertSame(500, $cloison->getTrace()->fin->x);
        self::assertSame(2500, $cloison->getTrace()->fin->y);

        $mur = new Segment(new Point2D(0, 0), new Point2D(4000, 0));
        $cloison->definirTrace(new TraceCloison(new Point2D(500, 1500), new Point2D(1500, 1500)));
        $service->decaler($cloison, $mur, 1000);
        self::assertSame(1000, $cloison->getTrace()->debut->y);
        self::assertCount(2, $cloison->getFaces());
    }

    public function testCloisonOuvertureHorsFaceEstSignalee(): void
    {
        $piece = $this->rectangle();
        $cloison = new Cloison($piece);
        $cloison->setNom('Cloison');
        $cloison->setEpaisseur(100);
        $this->em->persist($cloison);
        $cloison->definirTrace(new TraceCloison(new Point2D(0, 1500), new Point2D(2000, 1500)));
        $service = new CloisonEditionService($this->em, new PieceJonctions());
        $service->regenerer($cloison);
        $o = new Ouverture(null, $cloison);
        $o->setNom('Porte');
        $o->definirRectangle(1000, 0, 900, 2100);
        $this->em->flush();

        $plan = $service->planifier($cloison, $cloison->getTrace()->definirLongueur(1500));
        self::assertContains('opening_outside_surface', self::codes($plan->impacts));
        self::assertSame(2000, $cloison->getTrace()->longueur(), 'planifier ne modifie pas');
        $service->definirLongueur($cloison, 1500);
        self::assertSame(1500, $cloison->getTrace()->longueur());
        self::assertCount(1, $cloison->getOuvertures());
    }

    public function testCloisonAnchreeSuitLaCloisonDeplacee(): void
    {
        $piece = $this->rectangle();
        $service = new CloisonEditionService($this->em, new PieceJonctions());
        $a = new Cloison($piece);
        $a->setNom('A');
        $a->setEpaisseur(100);
        $this->em->persist($a);
        $a->definirTrace(new TraceCloison(new Point2D(0, 1500), new Point2D(3000, 1500)));
        $service->regenerer($a);
        $this->em->flush();

        $b = new Cloison($piece);
        $b->setNom('B');
        $b->setEpaisseur(100);
        $this->em->persist($b);
        $b->definirTrace(new TraceCloison(new Point2D(1000, 1500 + 50), new Point2D(1000, 3000)));
        $plan = $service->regenerer($b);
        self::assertSame('cloison', $b->getAncrageDebut()['type'], implode(',', self::codes($plan->impacts)));
        self::assertSame($a->getId(), $b->getAncrageDebut()['cible']);

        $service->deplacerExtremite($a, false, new Point2D(3000, 1000));
        // B reste accrochée à A (sa position 1000 le long de A) : son début a suivi
        self::assertNotSame(1500 + 50, $b->getTrace()->debut->y);
        self::assertSame('cloison', $b->getAncrageDebut()['type']);

        $avertissements = (new SuppressionCloison($this->em))->supprimerAvecAvertissements($a);
        self::assertSame(['partition_anchor_released'], self::codes($avertissements));
        self::assertSame('libre', $b->getAncrageDebut()['type']);
    }
}
