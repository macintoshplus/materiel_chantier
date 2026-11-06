<?php

declare(strict_types=1);

namespace App\Import;

use App\Geometrie\Point2D;

/**
 * Libellé du plan (TEXT/MTEXT) : aide au nommage, sans valeur géométrique.
 */
final readonly class TexteImporte
{
    public function __construct(public string $texte, public string $calque, public Point2D $position)
    {
    }
}
