<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Piece;
use App\Geometrie\Avertissement;

/**
 * Pièce créée et rapport d'import : avertissements (géométrie, placement), équipements conservés dans le rapport
 * seulement, nombre d'ouvertures créées depuis les blocs.
 */
final readonly class ResultatImport
{
    /**
     * @param list<Avertissement> $avertissements
     * @param list<BlocImporte>   $equipements
     */
    public function __construct(
        public Piece $piece,
        public array $avertissements,
        public array $equipements,
        public int $ouvertures,
    ) {
    }
}
