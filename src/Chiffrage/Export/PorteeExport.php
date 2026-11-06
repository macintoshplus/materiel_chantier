<?php

declare(strict_types=1);

namespace App\Chiffrage\Export;

/**
 * Étendue d'une liste de courses : par pièce (`cible` = nom de la pièce, toutes si absent), par fournisseur (`cible`
 * = nom du fournisseur, tous si absent ; `piece` limite à une pièce) ou récapitulatif du projet.
 */
final readonly class PorteeExport
{
    public function __construct(
        public TypeExport $type,
        public ?string $cible = null,
        public Regroupement $regroupement = Regroupement::Categorie,
        public ?string $piece = null,
    ) {
    }
}
