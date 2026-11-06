<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Lecture d'un article par catégorie et par chemin (colonne ou clé pointée du JSON).
 */
interface MaterielLisible
{
    public function categorie(): string;

    public function valeur(string $chemin): mixed;
}
