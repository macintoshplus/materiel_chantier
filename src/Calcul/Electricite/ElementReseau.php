<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

/**
 * Élément électrique vu par le domaine : un type, une position, et le type de boîte que sa surface impose.
 */
final readonly class ElementReseau
{
    public function __construct(
        public string $cle,
        public string $libelle,
        public TypeElement $type,
        public PointCheminement $point,
        public string $typeBoite = 'encastrement',
    ) {
    }
}
