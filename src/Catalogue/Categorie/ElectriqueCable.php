<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class ElectriqueCable implements CategorieMateriau
{
    public function code(): string
    {
        return 'electrique_cable';
    }

    public function libelle(): string
    {
        return 'Electrical cable';
    }

    public function margeDefautPct(): string
    {
        return '5.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('section', TypeChamp::Decimal, true, 'mm2', [], [], [], 0, null),
            new ChampCaracteristique('nb_conducteurs', TypeChamp::Entier, false, null, [], [], [], 1, null),
            new ChampCaracteristique('usage', TypeChamp::Texte, false, null, [], [], [], null, null),
        ];
    }
}
