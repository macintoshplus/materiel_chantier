<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 21/02/2026 22:36
 */

declare(strict_types=1);


namespace App\Service;

final class OuvertureOverlapChecker
{
    /**
     * @param list<array{distance_gauche: float, largeur: float, elevation: float, hauteur: float}> $ouvertures
     * @return array<array{int,int}> Liste des paires en conflit
     */
    public function detecterChevauchements(array $ouvertures): array
    {
        $conflits = [];

        $count = count($ouvertures);

        for ($i = 0; $i < $count; $i++) {
            $a = $ouvertures[$i];

            $aXmin = $a['distance_gauche'];
            $aXmax = $aXmin + $a['largeur'];
            $aYmin = $a['elevation'];
            $aYmax = $aYmin + $a['hauteur'];

            for ($j = $i + 1; $j < $count; $j++) {
                $b = $ouvertures[$j];

                $bXmin = $b['distance_gauche'];
                $bXmax = $bXmin + $b['largeur'];
                $bYmin = $b['elevation'];
                $bYmax = $bYmin + $b['hauteur'];

                if (
                    $aXmin < $bXmax &&
                    $aXmax > $bXmin &&
                    $aYmin < $bYmax &&
                    $aYmax > $bYmin
                ) {
                    $conflits[] = [$i, $j];
                }
            }
        }

        return $conflits;
    }
}
