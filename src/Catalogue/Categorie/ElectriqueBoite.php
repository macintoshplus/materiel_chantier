<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class ElectriqueBoite implements CategorieMateriau
{
    public function code(): string
    {
        return 'electrique_boite';
    }

    public function libelle(): string
    {
        return 'Electrical box';
    }

    public function margeDefautPct(): string
    {
        return '0.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('type_boite', TypeChamp::Choix, true, null, ['encastrement', 'cloison_creuse', 'saillie'], [], [], null, null),
            new ChampCaracteristique('profondeur', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('diametre', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
        ];
    }
}
