<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class Enduit implements CategorieMateriau
{
    public function code(): string
    {
        return 'enduit';
    }

    public function libelle(): string
    {
        return 'Plaster or joint compound';
    }

    public function margeDefautPct(): string
    {
        return '10.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('usage', TypeChamp::Choix, true, null, ['joint', 'ratissage'], [], [], null, null),
            new ChampCaracteristique('consommation_kg_par_m2', TypeChamp::Decimal, false, 'kg/m2', [], [], [], 0, null),
            new ChampCaracteristique('consommation_kg_par_m2_par_mm', TypeChamp::Decimal, false, 'kg/m2/mm', [], [], [], 0, null),
        ];
    }
}
