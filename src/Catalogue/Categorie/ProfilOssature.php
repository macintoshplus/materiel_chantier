<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class ProfilOssature implements CategorieMateriau
{
    public function code(): string
    {
        return 'profil_ossature';
    }

    public function libelle(): string
    {
        return 'Framing profile';
    }

    public function margeDefautPct(): string
    {
        return '5.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('famille', TypeChamp::Choix, true, null, ['rail', 'lisse', 'montant', 'fourrure', 'corniere'], [], [], null, null),
            new ChampCaracteristique('serie', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('pose.entraxe_defaut', TypeChamp::Entier, false, 'mm', [], [], [], 1, null),
            new ChampCaracteristique('pose.portees', TypeChamp::Liste, false, null, [], [new ChampCaracteristique('renfort', TypeChamp::Choix, true, null, ['simple', 'double_accole', 'double_decale'], [], [], null, null), new ChampCaracteristique('orientation', TypeChamp::Choix, true, null, ['verticale', 'horizontale'], [], [], null, null), new ChampCaracteristique('entraxe', TypeChamp::Entier, true, 'mm', [], [], [], 1, null), new ChampCaracteristique('longueur_max', TypeChamp::Entier, true, 'mm', [], [], [], 1, null), new ChampCaracteristique('decalage_joints_min_mm', TypeChamp::Entier, false, null, [], [], [], 0, null), new ChampCaracteristique('pas_vis_assemblage_mm', TypeChamp::Entier, false, null, [], [], [], 1, null), new ChampCaracteristique('accessoires', TypeChamp::Liste, false, null, [], [new ChampCaracteristique('specification', TypeChamp::Texte, true, null, [], [], [], null, null), new ChampCaracteristique('par_joint', TypeChamp::Entier, false, null, [], [], [], 0, null), new ChampCaracteristique('pas_mm', TypeChamp::Entier, false, null, [], [], [], 1, null)], [], null, null)], ['renfort', 'orientation', 'entraxe'], null, null),
        ];
    }
}
