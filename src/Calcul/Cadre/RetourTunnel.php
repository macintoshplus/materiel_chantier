<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

use App\Calcul\Surface\OuvertureCalculable;
use App\Enum\TypePose;

/**
 * Retour de tunnel d'une ouverture de cloison : bande de plaque sur les côtés de l'ouverture (linteau, jambages,
 * et appui si l'ouverture ne descend pas jusqu'au sol) dont la profondeur couvre l'épaisseur totale de la cloison.
 */
final class RetourTunnel
{
    /**
     * @return int surface en mm² (0 si l'ouverture n'est pas posée en tunnel)
     */
    public static function surface(OuvertureCalculable $ouverture, int $epaisseurTotale, int $solV): int
    {
        if (TypePose::Tunnel !== $ouverture->typePose) {
            return 0;
        }
        $perimetre = $ouverture->forme->perimetre();
        $boite = $ouverture->forme->boiteEnglobante();
        if ($boite->minY <= $solV) {
            $perimetre -= $boite->largeur();
        }

        return max(0, $perimetre) * $epaisseurTotale;
    }
}
