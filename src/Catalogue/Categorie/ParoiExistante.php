<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class ParoiExistante implements CategorieMateriau
{
    public function code(): string
    {
        return 'paroi_existante';
    }

    public function libelle(): string
    {
        return 'Existing wall material';
    }

    public function margeDefautPct(): string
    {
        return '0.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('lambda', TypeChamp::Decimal, false, 'W/m.K', [], [], [], 0, null),
            new ChampCaracteristique('r_forfaitaire', TypeChamp::Decimal, false, 'm2.K/W', [], [], [], 0, null),
            new ChampCaracteristique('masse_volumique', TypeChamp::Decimal, false, 'kg/m3', [], [], [], 0, null),
            new ChampCaracteristique('nature', TypeChamp::Choix, false, null, ['parpaing', 'brique', 'pierre', 'beton', 'platre', 'bois', 'autre'], [], [], null, null),
        ];
    }
}
