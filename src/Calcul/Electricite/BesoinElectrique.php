<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

use App\Calcul\Couche\TypeBesoin;

/**
 * Quantité annexe d'une installation électrique, avant résolution de l'article : catégorie du catalogue, critères
 * d'égalité, nature (nombre, ou longueur en mm) et quantité.
 */
final readonly class BesoinElectrique
{
    /**
     * @param array<string, int|string> $criteres
     */
    public function __construct(
        public string $cle,
        public string $categorie,
        public array $criteres,
        public TypeBesoin $type,
        public int $quantite,
    ) {
    }
}
