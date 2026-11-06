<?php

declare(strict_types=1);

namespace App\Calcul\Systeme;

use App\Calcul\Couche\PositionCouche;
use App\Enum\TypeSurface;

/**
 * Plafond suspendu sous plaques (pan horizontal ou sous rampant) : suspentes, fourrures et lisse périphérique,
 * isolant dans le plénum, plaques. Paramètres : `entraxe` des fourrures, `pas_suspente`, `hauteur_plenum` (longueur
 * de suspente), `penetration_vis` (mm), `aretes_ouvertes` (arêtes `a:b` du contour sans lisse, séparées par des
 * virgules : faîtage, jonction avec un autre pan) et pas de vis des peaux (sinon ceux de la plaque). Le plafond
 * autoportant est hors v1.
 */
final class SystemePlafondSuspendu implements DefinitionSysteme
{
    public const string CODE = 'plafond_suspendu_placo';

    public function code(): string
    {
        return self::CODE;
    }

    public function libelle(): string
    {
        return 'Suspended plasterboard ceiling';
    }

    public function typesSurface(): array
    {
        return [TypeSurface::PlafondPan];
    }

    public function emplacements(): array
    {
        return [
            new EmplacementCouche('suspente', 'attache', 1, 1, 'suspente', 'attache.suspentes'),
            new EmplacementCouche('ossature', 'ossature', 1, 1, 'profil_ossature', 'ossature.plafond'),
            new EmplacementCouche('isolant', 'isolant', 0, 2, 'isolant', 'isolant.cadre', [PositionCouche::EntreOssature, PositionCouche::Continue], [0, 90]),
            new EmplacementCouche('parement', 'parement', 1, 2, 'plaque_platre', 'parement.plaques', [PositionCouche::SurOssature]),
            new EmplacementCouche('enduit_joint', 'enduit', 0, 1, 'enduit', 'enduit.plaques'),
            new EmplacementCouche('enduit_ratissage', 'enduit', 0, 1, 'enduit', 'enduit.plaques'),
            new EmplacementCouche('membrane', 'membrane', 0, 1, 'membrane', 'membrane.rouleaux'),
            new EmplacementCouche('bande_joint', 'bande_joint', 0, 2, 'bande_joint', 'bande.joint'),
        ];
    }

    public function parametres(): array
    {
        return [
            new ParametreSysteme('retour_profondeur', 'int', null),
            new ParametreSysteme('marge_bande_resiliente_pct', 'int', 0),
            new ParametreSysteme('entraxe', 'int', 600),
            new ParametreSysteme('pas_suspente', 'int', 1200),
            new ParametreSysteme('hauteur_plenum', 'int', 200),
            new ParametreSysteme('penetration_vis', 'int', 10),
            new ParametreSysteme('aretes_ouvertes', 'string', ''),
            new ParametreSysteme('pas_vis_premiere_peau', 'int', null),
            new ParametreSysteme('pas_vis_derniere_peau', 'int', null),
            new ParametreSysteme('pas_vis_peau_unique', 'int', null),
        ];
    }
}
