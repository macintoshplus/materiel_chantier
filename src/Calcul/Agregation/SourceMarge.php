<?php

declare(strict_types=1);

namespace App\Calcul\Agregation;

/**
 * Niveau de la priorité qui a fourni la marge d'un matériau (voir `01-catalogue.DONE.md`).
 */
enum SourceMarge: string
{
    case Chiffrage = 'chiffrage';
    case Projet = 'projet';
    case Materiau = 'materiau';
    case Categorie = 'categorie';
    case Globale = 'globale';
}
