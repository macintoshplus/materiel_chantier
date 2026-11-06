<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class AppuiFixation implements CategorieMateriau
{
    public function code(): string
    {
        return 'appui_fixation';
    }

    public function libelle(): string
    {
        return 'Fixing support';
    }

    public function margeDefautPct(): string
    {
        return '5.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('usage', TypeChamp::Texte, false, null, [], [], [], null, null),
            new ChampCaracteristique('pose.pas_defaut', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('pose.hauteur_defaut', TypeChamp::Entier, false, 'mm', [], [], [], 0, null),
        ];
    }
}
