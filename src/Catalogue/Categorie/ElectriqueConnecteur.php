<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class ElectriqueConnecteur implements CategorieMateriau
{
    public function code(): string
    {
        return 'electrique_connecteur';
    }

    public function libelle(): string
    {
        return 'Electrical connector';
    }

    public function margeDefautPct(): string
    {
        return '0.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('type', TypeChamp::Texte, true, null, [], [], [], null, null),
            new ChampCaracteristique('nb_entrees', TypeChamp::Entier, false, null, [], [], [], 1, null),
        ];
    }
}
