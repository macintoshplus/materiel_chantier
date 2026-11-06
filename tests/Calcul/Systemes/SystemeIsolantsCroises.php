<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Systemes;

use App\Calcul\Couche\PositionCouche;
use App\Calcul\Systeme\DefinitionSysteme;
use App\Calcul\Systeme\EmplacementCouche;
use App\Enum\TypeSurface;

/**
 * Système de test (pas un système réel) : attaches, 1 à 2 isolants (dont un croisé), ossature, 1 à 2 peaux de plaque.
 * Il prouve qu'un nouveau système s'ajoute sans toucher au moteur ni aux entités.
 */
final class SystemeIsolantsCroises implements DefinitionSysteme
{
    public const string CODE = 'test_isolants_croises';

    public function code(): string
    {
        return self::CODE;
    }

    public function libelle(): string
    {
        return 'Test system: crossed insulations';
    }

    public function typesSurface(): array
    {
        return [TypeSurface::Mur, TypeSurface::PlafondPan, TypeSurface::CloisonFace];
    }

    public function emplacements(): array
    {
        return [
            new EmplacementCouche('attache', 'attache', 0, 1, 'appui_fixation', 'test.attache'),
            new EmplacementCouche('isolant', 'isolant', 1, 2, 'isolant', 'test.isolant', [PositionCouche::EntreOssature, PositionCouche::Continue], [0, 90]),
            new EmplacementCouche('ossature', 'ossature', 1, 1, 'profil_ossature', 'test.ossature'),
            new EmplacementCouche('parement', 'parement', 1, 2, 'plaque_platre', 'test.parement', [PositionCouche::SurOssature]),
        ];
    }

    public function parametres(): array
    {
        return [];
    }
}
