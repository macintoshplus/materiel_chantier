<?php

declare(strict_types=1);

namespace App\Calcul\Systeme;

use App\Calcul\Couche\PositionCouche;
use App\Enum\TypeSurface;

/**
 * Doublage sur ossature : fixé au mur par des appuis. Le profilé périphérique est un rail (système de type
 * cloison) ou une lisse (système de type doublage) : `type_profil_peripherique`. Une cloison qui s'y raccorde
 * ajoute un montant à chaque position de `positions_raccordement` (mm depuis le bord gauche, séparées par des virgules).
 */
final class SystemeDoublageOssature implements DefinitionSysteme
{
    public const string CODE = 'doublage_ossature';

    public function code(): string
    {
        return self::CODE;
    }

    public function libelle(): string
    {
        return 'Lining on framing';
    }

    public function typesSurface(): array
    {
        return [TypeSurface::Mur, TypeSurface::PlafondPan];
    }

    public function emplacements(): array
    {
        return [
            new EmplacementCouche('ossature', 'ossature', 1, 1, 'profil_ossature', 'ossature.cadre'),
            new EmplacementCouche('attache', 'attache', 1, 1, 'appui_fixation', 'attache.appuis'),
            new EmplacementCouche('isolant', 'isolant', 1, 2, 'isolant', 'isolant.cadre', [PositionCouche::EntreOssature, PositionCouche::Continue], [0, 90]),
            new EmplacementCouche('parement', 'parement', 1, 2, 'plaque_platre', 'parement.plaques', [PositionCouche::SurOssature]),
        ];
    }

    public function parametres(): array
    {
        return [
            new ParametreSysteme('type_profil_peripherique', 'string', 'rail'),
            new ParametreSysteme('montants_raccordement', 'int', 1),
            new ParametreSysteme('positions_raccordement', 'string', ''),
        ];
    }
}
