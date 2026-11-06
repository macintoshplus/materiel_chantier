<?php

declare(strict_types=1);

namespace App\Calcul\Systeme;

use App\Enum\TypeSurface;

/**
 * Un système est un assemblage ordonné de couches (emplacements), pas un calculateur monolithique.
 * Ajouter un système = une classe + le tag `app.definition_systeme` (posé par l'autoconfiguration).
 */
interface DefinitionSysteme
{
    public function code(): string;

    /** Libellé en anglais (traduit par l'application). */
    public function libelle(): string;

    /**
     * Types de surface compatibles : un mur, un pan et un plafond horizontal sont la même géométrie.
     *
     * @return list<TypeSurface>
     */
    public function typesSurface(): array;

    /**
     * Emplacements dans l'ordre de pose, du plus proche du support au plus visible.
     *
     * @return list<EmplacementCouche>
     */
    public function emplacements(): array;

    /**
     * @return list<ParametreSysteme>
     */
    public function parametres(): array;
}
