<?php

declare(strict_types=1);

namespace App\Calcul\Systeme;

use App\Calcul\Couche\PositionCouche;
use App\Enum\TypeSurface;

/**
 * Cloison sur ossature métallique : une ossature et un isolant communs aux deux faces (couches sans face), un à
 * deux parements par face. Les extrémités (`extremite_debut`, `extremite_fin`) sont déduites des ancrages de la
 * cloison au moment du calcul.
 */
final class SystemeCloisonOssature implements DefinitionSysteme
{
    public const string CODE = 'cloison_ossature';

    public function code(): string
    {
        return self::CODE;
    }

    public function libelle(): string
    {
        return 'Partition on metal framing';
    }

    public function typesSurface(): array
    {
        return [TypeSurface::CloisonFace];
    }

    public function emplacements(): array
    {
        return [
            new EmplacementCouche('ossature', 'ossature', 1, 1, 'profil_ossature', 'ossature.cadre'),
            new EmplacementCouche('isolant', 'isolant', 0, 2, 'isolant', 'isolant.cadre', [PositionCouche::EntreOssature, PositionCouche::Continue], [0, 90]),
            new EmplacementCouche('parement', 'parement', 1, 2, 'plaque_platre', 'parement.plaques', [PositionCouche::SurOssature]),
            new EmplacementCouche('enduit_joint', 'enduit', 0, 1, 'enduit', 'enduit.plaques'),
            new EmplacementCouche('enduit_ratissage', 'enduit', 0, 1, 'enduit', 'enduit.plaques'),
            new EmplacementCouche('membrane', 'membrane', 0, 1, 'membrane', 'membrane.rouleaux'),
            new EmplacementCouche('bande_joint', 'bande_joint', 0, 2, 'bande_joint', 'bande.joint'),
            new EmplacementCouche('fixation_support', 'fixation_support', 0, 1, 'fixation_support', 'fixation.support'),
            new EmplacementCouche('bande_resiliente', 'bande_resiliente', 0, 1, 'bande_resiliente', 'bande.resiliente'),
        ];
    }

    public function parametres(): array
    {
        return [
            new ParametreSysteme('retour_profondeur', 'int', null),
            new ParametreSysteme('marge_bande_resiliente_pct', 'int', 0),
            new ParametreSysteme('montants_raccordement', 'int', 1),
            new ParametreSysteme('extremite_debut', 'string', 'mur'),
            new ParametreSysteme('extremite_fin', 'string', 'mur'),
            new ParametreSysteme('renfort', 'string', ''),
            new ParametreSysteme('renfort_zone', 'string', 'toutes_positions'),
        ];
    }
}
