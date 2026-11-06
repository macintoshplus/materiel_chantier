<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class BandeJoint implements CategorieMateriau
{
    public function code(): string
    {
        return 'bande_joint';
    }

    public function libelle(): string
    {
        return 'Joint tape';
    }

    public function margeDefautPct(): string
    {
        return '10.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('type', TypeChamp::Choix, true, null, ['papier', 'armee', 'plat'], [], [], null, null),
            new ChampCaracteristique('longueur_rouleau', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
        ];
    }
}
