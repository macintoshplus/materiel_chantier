<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class ElectriqueGaine implements CategorieMateriau
{
    public function code(): string
    {
        return 'electrique_gaine';
    }

    public function libelle(): string
    {
        return 'Electrical conduit';
    }

    public function margeDefautPct(): string
    {
        return '5.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('diametre', TypeChamp::Entier, true, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('taux_remplissage_max', TypeChamp::Entier, false, '%', [], [], [], 1, 100),
            new ChampCaracteristique('conducteurs_max', TypeChamp::Liste, false, null, [], [
                new ChampCaracteristique('section', TypeChamp::Texte, true, 'mm2', [], [], [], null, null),
                new ChampCaracteristique('nombre', TypeChamp::Entier, true, null, [], [], [], 1, null),
            ], ['section'], null, null),
        ];
    }
}
