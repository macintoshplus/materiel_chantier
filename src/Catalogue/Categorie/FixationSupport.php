<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class FixationSupport implements CategorieMateriau
{
    public function code(): string
    {
        return 'fixation_support';
    }

    public function libelle(): string
    {
        return 'Support fixing';
    }

    public function margeDefautPct(): string
    {
        return '5.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('type', TypeChamp::Choix, true, null, ['cheville', 'vis_beton', 'autre'], [], [], null, null),
            new ChampCaracteristique('support', TypeChamp::Choix, false, null, ['beton', 'parpaing', 'brique', 'pierre', 'bois'], [], [], null, null),
            new ChampCaracteristique('charge_max', TypeChamp::Decimal, false, 'kg', [], [], [], 0, null),
            new ChampCaracteristique('pose.pas_mm', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('pose.nombre_par_m', TypeChamp::Decimal, false, '1/m', [], [], [], 0, null),
        ];
    }
}
