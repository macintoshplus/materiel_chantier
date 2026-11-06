<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Agregation\ResultatAgregation;

/**
 * Résultat d'agrégation d'une étendue (le projet, une pièce ou une surface), nommé pour l'affichage.
 */
final readonly class GroupeAchats
{
    public function __construct(public string $nom, public ResultatAgregation $resultat)
    {
    }
}
