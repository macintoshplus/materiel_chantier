<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class BandeResiliente implements CategorieMateriau
{
    public function code(): string
    {
        return 'bande_resiliente';
    }

    public function libelle(): string
    {
        return 'Resilient strip';
    }

    public function margeDefautPct(): string
    {
        return '5.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('epaisseur', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('largeur', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('longueur_rouleau', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
        ];
    }
}
