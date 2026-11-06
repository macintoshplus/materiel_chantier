<?php

declare(strict_types=1);

namespace App\Catalogue;

enum TypeChamp: string
{
    case Entier = 'int';
    case Decimal = 'float';
    case Texte = 'string';
    case Choix = 'enum';
    case Booleen = 'bool';
    case Liste = 'liste';
}
