<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class PlomberieTube implements CategorieMateriau
{
    public function code(): string
    {
        return 'plomberie_tube';
    }

    public function libelle(): string
    {
        return 'Plumbing pipe';
    }

    public function margeDefautPct(): string
    {
        return '5.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('diametre', TypeChamp::Entier, true, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('matiere', TypeChamp::Texte, false, null, [], [], [], null, null),
            new ChampCaracteristique('usage', TypeChamp::Choix, false, null, ['eau_froide', 'eau_chaude', 'evacuation'], [], [], null, null),
        ];
    }
}
