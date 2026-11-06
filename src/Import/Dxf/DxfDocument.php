<?php

declare(strict_types=1);

namespace App\Import\Dxf;

/**
 * Contenu neutre d'un fichier DXF : variables d'en-tête, définitions de blocs, entités de l'espace objet.
 */
final readonly class DxfDocument
{
    /**
     * @param array<string, string>  $variables nom (`$INSUNITS`) => première valeur
     * @param array<string, DxfBloc> $blocs
     * @param list<DxfEntite>        $entites
     */
    public function __construct(public array $variables, public array $blocs, public array $entites, public int $nombreEntites = 0)
    {
    }

    public function variable(string $nom): ?string
    {
        return $this->variables[$nom] ?? null;
    }
}
