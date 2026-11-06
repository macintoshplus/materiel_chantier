<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Schéma d'une catégorie de matériau. Ajouter une catégorie = ajouter une classe (tag `app.categorie_materiau`).
 */
interface CategorieMateriau
{
    public function code(): string;

    public function libelle(): string;

    /**
     * @return list<ChampCaracteristique>
     */
    public function champs(): array;

    /**
     * Marge de sécurité par défaut de la catégorie, en pourcents.
     */
    public function margeDefautPct(): string;
}
