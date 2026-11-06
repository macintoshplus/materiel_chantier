<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Structure neutre produite par toutes les sources (DXF, CSV) et consommée par `PieceImportService` : contours en mm,
 * blocs positionnés, textes, rapport. Indépendante du format d'origine.
 */
final readonly class PlanImporte
{
    /**
     * @param list<ContourImporte> $contours
     * @param list<BlocImporte>    $blocs
     * @param list<TexteImporte>   $textes
     * @param list<string>         $calques calques portant de la géométrie, du texte ou des blocs
     */
    public function __construct(
        public array $contours,
        public array $blocs,
        public array $textes,
        public RapportPlan $rapport,
        public array $calques = [],
        public ?UniteImport $unite = null,
        public bool $uniteDevinee = false,
    ) {
    }

    /**
     * @return list<ContourImporte>
     */
    public function contoursSelectionnables(): array
    {
        return array_values(array_filter($this->contours, static fn (ContourImporte $c): bool => $c->selectionnable()));
    }
}
