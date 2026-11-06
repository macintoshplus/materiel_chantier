<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeStrategie: string
{
    case FournisseurUnique = 'fournisseur_unique';
    case MeilleurPrix = 'meilleur_prix';
    case PlusProche = 'plus_proche';
    case Manuelle = 'manuelle';
}
