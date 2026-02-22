<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 21/02/2026 22:26
 */

declare(strict_types=1);

namespace App\Constraints;


use App\Model\Mur;
use App\Service\OuvertureOverlapChecker;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class PositionsIsValidValidator extends ConstraintValidator
{
    public function __construct(private readonly OuvertureOverlapChecker $checker)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($constraint instanceof PositionsIsValid === false) {
            throw new \InvalidArgumentException(sprintf('This constraint is not supported by this validator for constraint "%s".',
                get_class($constraint)));
        }

        if ($value instanceof Mur === false) {
            return;
        }

        $conflit = $this->checker->detecterChevauchements($value->getOuvertures());
        foreach ($conflit as $c) {
            $this->context->buildViolation('This position overlaps with %other%',
                ['%other%' => $c[1]])->atPath('ouvertures[' . $c[0] . ']')->addViolation();
        }

        foreach ($value->getOuvertures() as $key => $ouverture) {
            if ($ouverture['distance_gauche'] < $value->getEcartDebutEnMetre()) {
                $this->context->buildViolation('L\'ouverture démarre en dehors du doublage',
                    ['%key%' => $key])->atPath('ouvertures[' . $key . '][distance_gauche]')->addViolation();
            }

            if (($ouverture['distance_gauche'] + $ouverture['largeur']) > ($value->getLargeur() - $value->getEcartFinEnMetre())) {
                $this->context->buildViolation('L\'ouverture se termine en dehors du doublage')
                    ->atPath('ouvertures[' . $key . ']')->addViolation();
            }

            if ($ouverture['elevation'] < 0) {
                $this->context->buildViolation('L\'ouverture est en dessous du sol')->atPath('ouvertures[' . $key . '][elevation]')->addViolation();
            }

            if (($ouverture['elevation'] + $ouverture['hauteur']) > $value->getHauteur()) {
                $this->context->buildViolation('L\'ouverture est au dessus du plafond')->atPath('ouvertures[' . $key . '][elevation]')->addViolation();
            }

        }

    }
}
