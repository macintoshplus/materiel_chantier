<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class AccessoireOssature implements CategorieMateriau
{
    public function code(): string
    {
        return 'accessoire_ossature';
    }

    public function libelle(): string
    {
        return 'Framing accessory';
    }

    public function margeDefautPct(): string
    {
        return '5.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('usage', TypeChamp::Choix, true, null, ['liaison_montants_decales', 'liaison_montants_accoles', 'clip'], [], [], null, null),
            new ChampCaracteristique('renfort_compatible', TypeChamp::Choix, false, null, ['simple', 'double_accole', 'double_decale'], [], [], null, null),
        ];
    }
}
