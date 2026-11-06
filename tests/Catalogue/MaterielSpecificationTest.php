<?php

declare(strict_types=1);

namespace App\Tests\Catalogue;

use App\Catalogue\Categorie\Isolant;
use App\Catalogue\Critere;
use App\Catalogue\MaterielSpecification;
use App\Catalogue\Operateur;
use App\Entity\Materiau;
use PHPUnit\Framework\TestCase;

final class MaterielSpecificationTest extends TestCase
{
    private function isolant(float $lambda, int $epaisseur): Materiau
    {
        $m = new Materiau();
        $m->setCategorie('isolant');
        $m->setEpaisseur($epaisseur);
        $m->setCaracteristiques(['lambda' => $lambda]);

        return $m;
    }

    public function testLesCriteresFiltrentSurColonneEtJson(): void
    {
        $spec = MaterielSpecification::creer('insulation', new Isolant(), [
            new Critere('lambda', Operateur::InferieurOuEgal, 0.035),
            new Critere('epaisseur', Operateur::SuperieurOuEgal, 100),
        ]);

        self::assertTrue($spec->estSatisfaitePar($this->isolant(0.032, 100)));
        self::assertFalse($spec->estSatisfaitePar($this->isolant(0.040, 120)));
        self::assertFalse($spec->estSatisfaitePar($this->isolant(0.032, 80)));
    }

    public function testUneCleInconnueEstRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MaterielSpecification::creer('insulation', new Isolant(), [new Critere('inconnue', Operateur::Egal, 1)]);
    }

    public function testLaReferenceAuContexteEstResolue(): void
    {
        $spec = MaterielSpecification::creer('insulation', new Isolant(), [
            new Critere('epaisseur', Operateur::Egal, '{contexte.epaisseur}'),
        ]);

        self::assertFalse($spec->estResolue());
        $resolue = $spec->resoudre(['epaisseur' => 100]);
        self::assertTrue($resolue->estResolue());
        self::assertTrue($resolue->estSatisfaitePar($this->isolant(0.03, 100)));
        self::assertFalse($resolue->estSatisfaitePar($this->isolant(0.03, 90)));
    }

    public function testUneSpecificationNonResolueNePeutPasFiltrer(): void
    {
        $spec = MaterielSpecification::creer('insulation', new Isolant(), [
            new Critere('epaisseur', Operateur::Egal, '{contexte.epaisseur}'),
        ]);

        $this->expectException(\LogicException::class);
        $spec->estSatisfaitePar($this->isolant(0.03, 100));
    }

    public function testUneValeurDeContexteManquanteEstRefusee(): void
    {
        $spec = MaterielSpecification::creer('insulation', new Isolant(), [
            new Critere('epaisseur', Operateur::Egal, '{contexte.epaisseur}'),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $spec->resoudre([]);
    }

    public function testUneAutreCategorieNeCorrespondPas(): void
    {
        $m = $this->isolant(0.03, 100);
        $m->setCategorie('membrane');

        self::assertFalse(MaterielSpecification::creer('insulation', new Isolant(), [])->estSatisfaitePar($m));
    }
}
