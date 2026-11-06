<?php

declare(strict_types=1);

namespace App\Calcul\Materiau;

use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;

/**
 * Résout une couche non épinglée : référence au contexte remplacée, puis premier candidat du catalogue.
 */
final readonly class ResolveurMateriau
{
    public function __construct(private SourceCandidats $source)
    {
    }

    /**
     * @param array<string, scalar> $contexte
     */
    public function resoudre(MaterielSpecification $specification, array $contexte = []): ?MaterielLisible
    {
        $resolue = $specification->estResolue() ? $specification : $specification->resoudre($contexte);

        return $this->source->candidats($resolue)[0] ?? null;
    }
}
