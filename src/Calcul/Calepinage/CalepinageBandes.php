<?php

declare(strict_types=1);

namespace App\Calcul\Calepinage;

use App\Calcul\Cadre\BandeRetour;
use App\Calcul\Optimisation\OptimiseurCoupes;

/**
 * Plaques nécessaires aux bandes de retour. Chaque bande est découpée dans le sens de la longueur de la plaque : une
 * plaque donne `floor(largeur plaque / largeur bande)` rangées de la longueur de la plaque, et les bandes sont rangées
 * par l'optimisation de coupes 1D (`OptimiseurCoupes`). Une bande plus large que la plaque est comptée à la surface.
 */
final class CalepinageBandes
{
    /**
     * @param list<BandeRetour> $bandes
     */
    public function plaques(array $bandes, int $largeurPlaque, int $longueurPlaque): int
    {
        if ($largeurPlaque <= 0 || $longueurPlaque <= 0) {
            throw new \InvalidArgumentException('The board dimensions must be positive.');
        }
        $parLargeur = [];
        foreach ($bandes as $bande) {
            $parLargeur[$bande->largeur][] = $bande;
        }
        $total = 0;
        foreach ($parLargeur as $largeur => $groupe) {
            if ($largeur > $largeurPlaque) {
                $surface = array_sum(array_map(static fn (BandeRetour $b): int => $b->surface(), $groupe));
                $total += (int) ceil($surface / ($largeurPlaque * $longueurPlaque));

                continue;
            }
            $rangees = OptimiseurCoupes::barres(array_map(static fn (BandeRetour $b): int => $b->longueur, $groupe), $longueurPlaque);
            $total += (int) ceil($rangees / intdiv($largeurPlaque, $largeur));
        }

        return $total;
    }
}
