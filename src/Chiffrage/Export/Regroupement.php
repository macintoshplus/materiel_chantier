<?php

declare(strict_types=1);

namespace App\Chiffrage\Export;

enum Regroupement: string
{
    case Categorie = 'categorie';
    case Piece = 'piece';
}
