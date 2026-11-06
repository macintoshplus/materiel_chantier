<?php

declare(strict_types=1);

namespace App\Calcul\Pose;

/**
 * Origine d'un paramètre de pose retenu : surcharge de l'utilisateur, défaut du système ou limite d'un article.
 */
enum SourceValeur: string
{
    case Utilisateur = 'utilisateur';
    case Systeme = 'systeme';
    case Article = 'article';
}
