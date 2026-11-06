<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class Visserie implements CategorieMateriau
{
    public function code(): string
    {
        return 'visserie';
    }

    public function libelle(): string
    {
        return 'Screws';
    }

    public function margeDefautPct(): string
    {
        return '10.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('longueur', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('usage', TypeChamp::Choix, true, null, ['plaque_sur_metal', 'plaque_sur_bois', 'assemblage_metal'], [], [], null, null),
            new ChampCaracteristique('pose.epaisseur_fixable_min', TypeChamp::Entier, true, 'mm', [], [], [], 0, null),
            new ChampCaracteristique('pose.epaisseur_fixable_max', TypeChamp::Entier, true, 'mm', [], [], [], 0, null),
            new ChampCaracteristique('pose.consommation_par_m2', TypeChamp::Decimal, false, '1/m2', [], [], [], 0, null),
        ];
    }
}
