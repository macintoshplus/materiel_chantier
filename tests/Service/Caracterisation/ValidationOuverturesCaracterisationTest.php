<?php

declare(strict_types=1);

namespace App\Tests\Service\Caracterisation;

use App\Constraints\PositionsIsValid;
use App\Constraints\PositionsIsValidValidator;
use App\Service\OuvertureOverlapChecker;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/** @extends ConstraintValidatorTestCase<PositionsIsValidValidator> */
final class ValidationOuverturesCaracterisationTest extends ConstraintValidatorTestCase
{
    use ScenariosTrait;

    protected function createValidator(): PositionsIsValidValidator
    {
        return new PositionsIsValidValidator(new OuvertureOverlapChecker());
    }

    public function testChevauchementDetecteEntreDeuxOuvertures(): void
    {
        $o = static fn (int $g, int $l, int $e, int $h) => ['distance_gauche' => $g, 'largeur' => $l, 'elevation' => $e, 'hauteur' => $h];
        $conflits = (new OuvertureOverlapChecker())->detecterChevauchements([
            $o(1000, 1000, 0, 1000),
            $o(1500, 1000, 500, 1000), // empiète sur la première
            $o(2000, 1000, 0, 1000), // touche la première par le bord : pas de chevauchement
        ]);

        self::assertSame([[0, 1], [1, 2]], $conflits);
    }

    public function testMurSansProblemeNeLeveAucuneViolation(): void
    {
        $this->validator->validate($this->murAvecOuvertures(), new PositionsIsValid());
        $this->assertNoViolation();
    }

    public function testOuvertureHorsDuMurEstRefusee(): void
    {
        $mur = $this->murPlein();
        $mur->setEcartDebut(48);
        $mur->setEcartFin(48);
        // Commence avant l'écart de gauche (0,048), dépasse du plafond (2,5) et du bord droit.
        $mur->addOuverture([
            'type' => 'fenetre', 'largeur' => 4000, 'hauteur' => 2000, 'distance_gauche' => 0, 'elevation' => 1000,
            'type_pose' => 'applique', 'type_contour' => 'aucun', 'refoncement_ouvrant' => 0,
        ]);

        $this->validator->validate($mur, new PositionsIsValid());

        $this->buildViolation('L\'ouverture démarre en dehors du doublage')
            ->setParameter('%key%', '0')
            ->atPath('property.path.ouvertures[0][distance_gauche]')
            ->buildNextViolation('L\'ouverture se termine en dehors du doublage')
            ->atPath('property.path.ouvertures[0]')
            ->buildNextViolation('L\'ouverture est au dessus du plafond')
            ->atPath('property.path.ouvertures[0][elevation]')
            ->assertRaised();
    }

    public function testOuvertureSousLeSolEstRefusee(): void
    {
        $mur = $this->murPlein();
        $mur->addOuverture([
            'type' => 'fenetre', 'largeur' => 1000, 'hauteur' => 1000, 'distance_gauche' => 1000, 'elevation' => -100,
            'type_pose' => 'applique', 'type_contour' => 'aucun', 'refoncement_ouvrant' => 0,
        ]);

        $this->validator->validate($mur, new PositionsIsValid());

        $this->buildViolation('L\'ouverture est en dessous du sol')
            ->atPath('property.path.ouvertures[0][elevation]')
            ->assertRaised();
    }
}
