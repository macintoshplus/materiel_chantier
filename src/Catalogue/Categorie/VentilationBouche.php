<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class VentilationBouche implements CategorieMateriau
{
    public function code(): string
    {
        return 'ventilation_bouche';
    }

    public function libelle(): string
    {
        return 'Ventilation vent';
    }

    public function margeDefautPct(): string
    {
        return '0.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('diametre', TypeChamp::Entier, true, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('debit', TypeChamp::Decimal, false, 'm3/h', [], [], [], 0, null),
        ];
    }
}
