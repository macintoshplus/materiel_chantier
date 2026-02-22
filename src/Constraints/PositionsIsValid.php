<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 21/02/2026 22:26
 */

declare(strict_types=1);

namespace App\Constraints;


use Symfony\Component\Validator\Constraint;

#[\Attribute()]
final class PositionsIsValid extends Constraint
{
public function getTargets(): string|array
{
    return self::CLASS_CONSTRAINT;
}
}
