<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class Suspente implements CategorieMateriau
{
    public function code(): string
    {
        return 'suspente';
    }

    public function libelle(): string
    {
        return 'Hanger';
    }

    public function margeDefautPct(): string
    {
        return '5.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('charge_max', TypeChamp::Decimal, false, 'kg', [], [], [], 0, null),
            new ChampCaracteristique('pas', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
        ];
    }
}
