<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 22/02/2026 00:24
 */

declare(strict_types=1);

namespace App\Model\Chantier;


final class Appuis
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
    ) {
    }
}
