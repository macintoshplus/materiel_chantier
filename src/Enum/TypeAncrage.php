<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeAncrage: string
{
    case Mur = 'mur';
    case Cloison = 'cloison';
    case Doublage = 'doublage';
    case Libre = 'libre';
}
