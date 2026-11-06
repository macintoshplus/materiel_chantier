<?php

declare(strict_types=1);

namespace App\Catalogue\Categorie;

use App\Catalogue\CategorieMateriau;
use App\Catalogue\ChampCaracteristique;
use App\Catalogue\TypeChamp;

final class Isolant implements CategorieMateriau
{
    public function code(): string
    {
        return 'isolant';
    }

    public function libelle(): string
    {
        return 'Insulation';
    }

    public function margeDefautPct(): string
    {
        return '5.00';
    }

    public function champs(): array
    {
        return [
            new ChampCaracteristique('lambda', TypeChamp::Decimal, true, 'W/m.K', [], [], [], 0, null),
            new ChampCaracteristique('r_certifie', TypeChamp::Decimal, false, 'm2.K/W', [], [], [], 0, null),
            new ChampCaracteristique('famille', TypeChamp::Choix, false, null, ['verre', 'roche', 'bois', 'synthetique', 'biosource'], [], [], null, null),
            new ChampCaracteristique('mise_en_oeuvre', TypeChamp::Choix, false, null, ['entre_ossature', 'sous_ossature'], [], [], null, null),
            new ChampCaracteristique('reaction_feu', TypeChamp::Texte, false, null, [], [], [], null, null),
        ];
    }
}
