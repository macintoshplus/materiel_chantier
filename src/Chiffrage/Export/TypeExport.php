<?php

declare(strict_types=1);

namespace App\Chiffrage\Export;

enum TypeExport: string
{
    case Piece = 'piece';
    case Fournisseur = 'fournisseur';
    case Projet = 'projet';
}
