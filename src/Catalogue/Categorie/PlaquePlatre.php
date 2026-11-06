<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class PlaquePlatre implements CategorieMateriau
{
    public function code(): string
    {
        return 'plaque_platre';
    }

    public function libelle(): string
    {
        return 'Plasterboard';
    }

    public function margeDefautPct(): string
    {
        return '10.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('type', TypeChamp::Choix, false, null, ['standard', 'hydrofuge', 'feu', 'haute_durete', 'phonique', 'flexible'], [], [], null, null),
            new ChampCaracteristique('bord', TypeChamp::Choix, false, null, ['BA', 'AB'], [], [], null, null),
            new ChampCaracteristique('densite', TypeChamp::Decimal, false, 'kg/m3', [], [], [], 0, null),
            new ChampCaracteristique('pose.entraxe_max', TypeChamp::Entier, true, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('pose.pas_vis_premiere_peau', TypeChamp::Entier, true, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('pose.pas_vis_derniere_peau', TypeChamp::Entier, true, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('pose.pas_vis_peau_unique', TypeChamp::Entier, true, 'mm', [], [], [], 1, null),
        ];
    }
}
