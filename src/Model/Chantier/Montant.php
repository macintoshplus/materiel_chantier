<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 20/02/2026 22:53
 */

declare(strict_types=1);

namespace App\Model\Chantier;


final class Montant
{
    public function __construct(
        public string $id,
        public int $x,
        public int $y,
        public int $hauteur,
        public bool $appuis,
    ) {
    }
}
