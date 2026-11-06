<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Entity\Piece;
use App\Entity\Projet;
use App\Enum\SourceMesures;
use App\Geometrie\ErreurGeometrique;
use App\Geometrie\Point2D;
use App\Import\ChoixImport;
use App\Import\ModeContour;
use App\Import\PieceImportService;
use App\Import\PlanImporte;
use App\Service\CloisonEditionService;
use App\Service\ControlePlacement;
use App\Service\OrdreSurfaces;
use App\Service\PieceCreationService;
use App\Service\PieceEditionService;
use App\Service\PieceJonctions;
use App\Tests\Support\BaseDeDonneesTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PieceImportServiceTest extends KernelTestCase
{
    use BaseDeDonneesTrait;

    private EntityManagerInterface $em;

    private function service(): PieceImportService
    {
        self::bootKernel();
        $this->em = $this->creerSchema();

        return new PieceImportService($this->em, new PieceCreationService(new PieceJonctions()), new ControlePlacement());
    }

    private function projet(): Projet
    {
        $projet = new Projet();
        $projet->setNom('P');
        $this->em->persist($projet);
        $this->em->flush();

        return $projet;
    }

    /**
     * @return iterable<string, array{PlanImporte, int}>
     */
    public static function sources(): iterable
    {
        yield 'dxf rectangle' => [Fixtures::dxf('rectangle.dxf'), 12_000_000];
        yield 'dxf L' => [Fixtures::dxf('l.dxf'), 14_000_000];
        yield 'dxf pan coupé' => [Fixtures::dxf('pan_coupe.dxf'), 11_500_000];
        yield 'csv L cotes' => [Fixtures::csv('l_cotes.csv'), 14_000_000];
        yield 'csv pan coupé xy' => [Fixtures::csv('pan_coupe_xy.csv'), 11_500_000];
    }

    #[DataProvider('sources')]
    public function testAireDeLaPieceCreee(PlanImporte $plan, int $aire): void
    {
        $service = $this->service();
        $resultat = $service->creer($this->projet(), $plan, new ChoixImport(0, nom: 'Salon'), SourceMesures::Dxf);
        $piece = $resultat->piece;
        self::assertNotNull($piece->getId());
        self::assertSame('Salon', $piece->getNom());
        self::assertEqualsWithDelta($aire, $piece->getPolygoneSol()->aire(), $aire * 0.001);
        $murs = $piece->getMurs();
        self::assertCount(\count($piece->getFormeSol()), $murs);
        self::assertSame('Wall 1', $murs[0]->getNom());
    }

    public function testDecalageVersLInterieur(): void
    {
        $service = $this->service();
        $plan = Fixtures::dxf('rectangle.dxf');
        $axe = $service->creer($this->projet(), $plan, new ChoixImport(0, mode: ModeContour::Axe, epaisseurMur: 200), SourceMesures::Dxf);
        self::assertSame(3800 * 2800, $axe->piece->getPolygoneSol()->aire());
        $ext = $service->creer($this->projet(), $plan, new ChoixImport(0, mode: ModeContour::Exterieur, epaisseurMur: 200), SourceMesures::Dxf);
        self::assertSame(3600 * 2600, $ext->piece->getPolygoneSol()->aire());
    }

    public function testBlocsDevenantOuvertures(): void
    {
        $service = $this->service();
        $plan = Fixtures::dxf('portes_fenetres.dxf');
        $resultat = $service->creer($this->projet(), $plan, new ChoixImport(0), SourceMesures::Dxf);
        self::assertSame(2, $resultat->ouvertures);
        self::assertCount(1, $resultat->equipements);
        $noms = [];
        foreach ($resultat->piece->getMurs() as $mur) {
            foreach ($mur->getOuvertures() as $o) {
                $noms[$o->getType()] = $o->getNom();
            }
        }
        self::assertSame(['porte' => 'Door 1', 'fenetre' => 'Window 1'], $noms);
    }

    public function testErreursSansEnregistrementPartiel(): void
    {
        $service = $this->service();
        $projet = $this->projet();
        $avant = $this->em->getRepository(Piece::class)->count([]);

        foreach ([
            [Fixtures::dxf('avec_arc.dxf'), new ChoixImport(0), 'import_curved_walls'],
            [Fixtures::dxf('rectangle.dxf'), new ChoixImport(5), 'import_contour_unknown'],
            [Fixtures::dxf('rectangle.dxf'), new ChoixImport(0, hauteurSousPlafond: 0), 'import_height_invalid'],
            [Fixtures::dxf('rectangle.dxf'), new ChoixImport(0, mode: ModeContour::Axe, epaisseurMur: 9000), 'import_offset_invalid'],
        ] as [$plan, $choix, $code]) {
            self::assertSame($code, Fixtures::codeErreur(static fn () => $service->creer($projet, $plan, $choix, SourceMesures::Dxf)), $code);
        }
        $this->em->clear();
        self::assertSame($avant, $this->em->getRepository(Piece::class)->count([]));
        self::assertCount(0, $this->em->getRepository(Projet::class)->findOneBy([])?->getPieces() ?? []);
    }

    public function testMursConserventNomEtOuverturesQuandUnSommetBouge(): void
    {
        $service = $this->service();
        $resultat = $service->creer($this->projet(), Fixtures::dxf('portes_fenetres.dxf'), new ChoixImport(0), SourceMesures::Dxf);
        $piece = $resultat->piece;
        $murs = OrdreSurfaces::murs($piece);
        $murs[0]->setNom('Mur de la porte');
        $this->em->flush();
        $ouverturesAvant = array_map(static fn ($m) => \count($m->getOuvertures()), $murs);
        $sol = $piece->getSol();
        self::assertNotNull($sol);
        $ids = $sol->getContour()->ids();
        $jonctions = new PieceJonctions();
        $edition = new PieceEditionService($this->em, $jonctions, new CloisonEditionService($this->em, $jonctions));
        $point = $sol->getContour()->point($ids[2]);
        $edition->appliquerDeplacement($piece, $ids[2], new Point2D($point->x + 200, $point->y + 100));

        $apres = OrdreSurfaces::murs($piece);
        self::assertSame('Mur de la porte', $apres[0]->getNom());
        self::assertSame($ouverturesAvant, array_map(static fn ($m) => \count($m->getOuvertures()), $apres));
        self::assertSame(\count($murs), \count($apres));
    }

    public function testErreurGeometriqueDuContourRejete(): void
    {
        $this->expectException(ErreurGeometrique::class);
        $this->service()->creer($this->projet(), Fixtures::dxf('avec_bulge.dxf'), new ChoixImport(0), SourceMesures::Dxf);
    }
}
