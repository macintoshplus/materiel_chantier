<?php

declare(strict_types=1);

namespace App\Calcul\Materiau;

use App\Catalogue\MaterielLisible;
use App\Catalogue\MaterielSpecification;

/**
 * Source d'articles candidats pour une spécification résolue (le catalogue en base, ou un double en test).
 */
interface SourceCandidats
{
    /**
     * @return list<MaterielLisible>
     */
    public function candidats(MaterielSpecification $specification): array;
}
