<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Cloison;
use App\Entity\Ouverture;
use App\Entity\Piece;
use App\Entity\Projet;
use App\Entity\Surface;
use App\Enum\CoteCloison;
use App\Enum\TypeSurface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidationEntitesTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    private function piece(): Piece
    {
        $projet = new Projet();
        $projet->setNom('P');

        return new Piece($projet);
    }

    public function testUneFaceDeCloisonExigeCloisonEtCote(): void
    {
        $piece = $this->piece();
        $face = new Surface($piece, TypeSurface::CloisonFace);
        self::assertCount(1, $this->validator->validate($face));

        $cloison = new Cloison($piece);
        $face->rattacherACloison($cloison, null);
        self::assertCount(1, $this->validator->validate($face));

        $face->rattacherACloison($cloison, CoteCloison::A);
        self::assertCount(0, $this->validator->validate($face));
    }

    public function testUnMurNePorteNiCloisonNiCote(): void
    {
        $piece = $this->piece();
        $mur = new Surface($piece, TypeSurface::Mur);
        self::assertCount(0, $this->validator->validate($mur));

        $mur->rattacherACloison(new Cloison($piece), CoteCloison::B);
        self::assertCount(1, $this->validator->validate($mur));
    }

    public function testUneOuvertureADeuxParentsOuAucunEstRefusee(): void
    {
        $piece = $this->piece();
        $surface = new Surface($piece, TypeSurface::Mur);
        $cloison = new Cloison($piece);

        self::assertCount(0, $this->validator->validate(new Ouverture($surface)));
        self::assertCount(0, $this->validator->validate(new Ouverture(null, $cloison)));
        self::assertCount(1, $this->validator->validate(new Ouverture()));
        self::assertCount(1, $this->validator->validate(new Ouverture($surface, $cloison)));
    }

    public function testLeRefoncementNeSauraitEtreNegatif(): void
    {
        $ouverture = new Ouverture(new Surface($this->piece(), TypeSurface::Mur));
        $ouverture->setRefoncement(-1);

        self::assertCount(1, $this->validator->validate($ouverture));
    }

    public function testUnProjetDoitAvoirUnNom(): void
    {
        self::assertCount(1, $this->validator->validate(new Projet()));
    }
}
