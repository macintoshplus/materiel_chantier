<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class VentilationGroupe implements CategorieMateriau
{
    public function code(): string
    {
        return 'ventilation_groupe';
    }

    public function libelle(): string
    {
        return 'Ventilation unit';
    }

    public function margeDefautPct(): string
    {
        return '0.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('diametre', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('debit', TypeChamp::Decimal, true, 'm3/h', [], [], [], 0, null),
        ];
    }
}
