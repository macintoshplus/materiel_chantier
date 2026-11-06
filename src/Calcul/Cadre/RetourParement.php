<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

use App\Calcul\Surface\OuvertureCalculable;
use App\Enum\TypePose;

/**
 * Retour de parement d'une ouverture posée en tunnel : une bande par côté (linteau, côtés et, si l'ouverture ne
 * descend pas jusqu'au sol, appui), de largeur `profondeurRetour`. En pose en applique, aucune bande.
 */
final class RetourParement
{
    /**
     * Profondeur du retour : épaisseur du complexe devant le support plus refoncement de l'ouverture.
     */
    public static function profondeur(int $epaisseurComplexe, OuvertureCalculable $ouverture): int
    {
        return max(0, $epaisseurComplexe + $ouverture->refoncement);
    }

    /**
     * @return list<BandeRetour>
     */
    public static function bandes(OuvertureCalculable $ouverture, int $profondeur, int $solV): array
    {
        if (TypePose::Tunnel !== $ouverture->typePose || $profondeur <= 0) {
            return [];
        }
        $boite = $ouverture->forme->boiteEnglobante();
        $bandes = [];
        foreach ($ouverture->forme->aretes() as $arete) {
            $longueur = $arete->longueur();
            if (0 === $longueur) {
                continue;
            }
            $horizontale = $arete->debut->y === $arete->fin->y;
            if ($horizontale && $arete->debut->y === $boite->minY) {
                if ($boite->minY <= $solV) {
                    continue;
                }
                $cote = 'appui';
            } elseif ($horizontale && $arete->debut->y === $boite->maxY) {
                $cote = 'linteau';
            } else {
                $cote = 'cote';
            }
            $bandes[] = new BandeRetour($cote, $longueur, $profondeur);
        }

        return $bandes;
    }

    /**
     * Angles rentrants entre deux bandes consécutives (retour contre retour) : longueur cumulée (mm), un angle de
     * la profondeur du retour par jonction. Les angles sortants (retour contre parement) suivent le contour :
     * longueur cumulée des bandes.
     *
     * @param list<BandeRetour> $bandes
     */
    public static function longueurAnglesRentrants(array $bandes, bool $ferme): int
    {
        if ([] === $bandes) {
            return 0;
        }
        $jonctions = $ferme ? \count($bandes) : \count($bandes) - 1;

        return max(0, $jonctions) * $bandes[0]->largeur;
    }

    /**
     * @param list<BandeRetour> $bandes
     */
    public static function longueurAnglesSortants(array $bandes): int
    {
        return array_sum(array_map(static fn (BandeRetour $b): int => $b->longueur, $bandes));
    }

    /**
     * Les bandes forment une boucle fermée sauf si l'appui manque (ouverture descendant jusqu'au sol).
     *
     * @param list<BandeRetour> $bandes
     */
    public static function estFerme(array $bandes): bool
    {
        foreach ($bandes as $b) {
            if ('appui' === $b->cote) {
                return true;
            }
        }

        return false;
    }
}
