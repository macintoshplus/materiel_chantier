<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class Membrane implements CategorieMateriau
{
    public function code(): string
    {
        return 'membrane';
    }

    public function libelle(): string
    {
        return 'Membrane';
    }

    public function margeDefautPct(): string
    {
        return '10.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('sd', TypeChamp::Decimal, true, 'm', [], [], [], 0, null),
            new ChampCaracteristique('usage', TypeChamp::Choix, true, null, ['pare_vapeur', 'frein_vapeur', 'pare_pluie'], [], [], null, null),
            new ChampCaracteristique('recouvrement_mm', TypeChamp::Entier, false, 'mm', [], [], [], 0, null),
        ];
    }
}
