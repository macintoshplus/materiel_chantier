<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Catalogue\Categorie\Isolant;
use App\Catalogue\Categorie\PlaquePlatre;
use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Entity\Materiau;
use App\Repository\MateriauRepository;
use App\Service\ImportateurDefauts;
use App\Tests\Support\BaseDeDonneesTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogueIntegrationTest extends KernelTestCase
{
    use BaseDeDonneesTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->creerSchema();
    }

    public function testLImportDesDefautsEstIdempotent(): void
    {
        $importateur = self::getContainer()->get(ImportateurDefauts::class);

        $premier = $importateur->importer(new \DateTimeImmutable());
        $second = $importateur->importer(new \DateTimeImmutable());

        self::assertGreaterThan(0, $premier['articles']);
        self::assertSame(['articles' => 0, 'prix' => 0], $second);
    }

    public function testLesCandidatsRespectentLesCriteres(): void
    {
        $em = $this->creerSchema();
        foreach ([[0.032, 100, 'ISO-A'], [0.040, 100, 'ISO-B'], [0.032, 60, 'ISO-C']] as [$lambda, $epaisseur, $reference]) {
            $m = new Materiau();
            $m->setReference($reference);
            $m->setLibelle($reference);
            $m->setCategorie('isolant');
            $m->setEpaisseur($epaisseur);
            $m->setCaracteristiques(['lambda' => $lambda]);
            $em->persist($m);
        }
        $em->flush();
        $depot = self::getContainer()->get(MateriauRepository::class);

        $candidats = $depot->candidats(MaterielSpecification::creer('insulation', new Isolant(), [
            new Critere('lambda', Operateur::InferieurOuEgal, 0.035),
            new Critere('epaisseur', Operateur::SuperieurOuEgal, 100),
        ]));

        self::assertSame(['ISO-A'], array_map(static fn (Materiau $m): string => $m->getReference(), $candidats));
        self::assertSame([], $depot->candidats(MaterielSpecification::creer('board', new PlaquePlatre(), [])));
    }
}
