<?php

declare(strict_types=1);

namespace App\Chiffrage\Export;

/**
 * Ligne d'une liste de courses. `quantite` est la quantité à acheter pour une ligne agrégée (unités achetées, en
 * `lots` lots) ou le besoin de la pièce pour une part de pièce (`lots` nul). Montants en centimes.
 */
final readonly class ItemExport
{
    public function __construct(
        public string $reference,
        public ?string $referenceFournisseur,
        public string $libelle,
        public string $dimensions,
        public int $quantite,
        public ?int $lots,
        public ?int $prixLot,
        public int $total,
        public bool $manquant,
        public ?string $remplace,
    ) {
    }
}
