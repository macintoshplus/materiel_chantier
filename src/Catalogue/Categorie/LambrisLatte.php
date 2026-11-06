<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class LambrisLatte implements CategorieMateriau
{
    public function code(): string
    {
        return 'lambris_latte';
    }

    public function libelle(): string
    {
        return 'Cladding or batten';
    }

    public function margeDefautPct(): string
    {
        return '10.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('essence', TypeChamp::Texte, false, null, [], [], [], null, null),
            new ChampCaracteristique('profil', TypeChamp::Texte, false, null, [], [], [], null, null),
            new ChampCaracteristique('largeur_utile', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
        ];
    }
}
