<?php

declare(strict_types=1);

namespace App\Tests\Calcul\Support;

use App\Calcul\Materiau\SourceCandidats;
use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;

final class SourceCandidatsDouble implements SourceCandidats
{
    /** @var list<MaterielSpecification> */
    public array $demandes = [];

    /**
     * @param list<MaterielLisible> $articles
     */
    public function __construct(private readonly array $articles)
    {
    }

    public function candidats(MaterielSpecification $specification): array
    {
        $this->demandes[] = $specification;

        return array_values(array_filter($this->articles, static fn (MaterielLisible $a): bool => $specification->estSatisfaitePar($a)));
    }
}
