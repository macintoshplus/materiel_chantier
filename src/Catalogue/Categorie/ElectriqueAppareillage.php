<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class ElectriqueAppareillage implements CategorieMateriau
{
    public function code(): string
    {
        return 'electrique_appareillage';
    }

    public function libelle(): string
    {
        return 'Electrical device';
    }

    public function margeDefautPct(): string
    {
        return '0.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('fonction', TypeChamp::Texte, true, null, [], [], [], null, null),
            new ChampCaracteristique('type_boite', TypeChamp::Texte, false, null, [], [], [], null, null),
        ];
    }
}
