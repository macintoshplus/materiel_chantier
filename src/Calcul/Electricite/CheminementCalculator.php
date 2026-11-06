<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

use App\Geometrie\Avertissement;

/**
 * Cheminement v1 d'un réseau : distance orthogonale, pièce par pièce. Le câble monte de l'élément au plafond, suit les
 * axes de la pièce, puis descend à l'élément suivant ; d'une pièce à l'autre il passe par le haut de la porte la plus
 * proche de chaque élément (pas de topologie entre pièces en v1, voir « Décisions et écarts » de l'étape 07).
 *
 * Câble d'un tronçon = longueur × (100 + réserve) % arrondie au mm supérieur + une queue à chaque extrémité (boîte).
 * Gaine d'un tronçon = sa longueur, dans le plus petit diamètre qui admet ses conducteurs.
 */
final class CheminementCalculator
{
    /** Queue de câble laissée dans chaque boîte, en mm. */
    public const int QUEUE_BOITE_MM = 200;

    /**
     * @param list<GaineDisponible>                 $gaines   gaines du catalogue
     * @param array<string, list<PointCheminement>> $passages hauts de porte par clé de pièce
     */
    public function calculer(ReseauCalcul $reseau, array $gaines, array $passages = []): ResultatCheminement
    {
        usort($gaines, static fn (GaineDisponible $a, GaineDisponible $b): int => $a->diametre <=> $b->diametre);
        $avertissements = [];
        $erreurs = [];

        if (!isset($reseau->elements[$reseau->depart])) {
            $erreurs[] = new Avertissement('electrical_start_missing', ['network' => $reseau->nom]);

            return ResultatCheminement::depuisTroncons($reseau->nom, $reseau->sectionCentiemes, [], $avertissements, $erreurs);
        }

        $troncons = [];
        $atteints = [$reseau->depart => true];
        $restantes = $reseau->liaisons;
        do {
            $progres = false;
            foreach ($restantes as $i => $liaison) {
                if (!isset($atteints[$liaison->parent]) || isset($atteints[$liaison->enfant])) {
                    continue;
                }
                unset($restantes[$i]);
                $progres = true;
                if (!isset($reseau->elements[$liaison->enfant])) {
                    $avertissements[] = new Avertissement('electrical_link_unknown', ['network' => $reseau->nom]);

                    continue;
                }
                $atteints[$liaison->enfant] = true;
                $troncon = $this->troncon($reseau, $liaison, $gaines, $passages, $avertissements, $erreurs);
                if (null !== $troncon) {
                    $troncons[] = $troncon;
                }
            }
        } while ($progres);
        foreach ($restantes as $liaison) {
            $avertissements[] = new Avertissement('electrical_unreachable', ['network' => $reseau->nom, 'element' => $reseau->elements[$liaison->enfant]->libelle ?? $liaison->enfant]);
        }

        return ResultatCheminement::depuisTroncons($reseau->nom, $reseau->sectionCentiemes, $troncons, $avertissements, $erreurs);
    }

    /**
     * Distance de cheminement (mm) entre deux points, ou null si les pièces diffèrent et qu'une porte manque.
     *
     * @param array<string, list<PointCheminement>> $passages
     */
    public function distance(PointCheminement $a, PointCheminement $b, array $passages = []): ?int
    {
        if ($a->piece === $b->piece) {
            return self::dansLaPiece($a, $b);
        }
        $porteA = self::porteLaPlusProche($a, $passages);
        $porteB = self::porteLaPlusProche($b, $passages);
        if (null === $porteA || null === $porteB) {
            return null;
        }

        return self::dansLaPiece($a, $porteA) + self::dansLaPiece($porteB, $b);
    }

    private static function dansLaPiece(PointCheminement $a, PointCheminement $b): int
    {
        return abs($a->x - $b->x) + abs($a->y - $b->y)
            + max(0, $a->hauteurPlafond - $a->z) + max(0, $b->hauteurPlafond - $b->z)
            + abs($a->hauteurPlafond - $b->hauteurPlafond);
    }

    /**
     * @param array<string, list<PointCheminement>> $passages
     */
    private static function porteLaPlusProche(PointCheminement $point, array $passages): ?PointCheminement
    {
        $meilleure = null;
        $distance = null;
        foreach ($passages[$point->piece] ?? [] as $porte) {
            $d = self::dansLaPiece($point, $porte);
            if (null === $distance || $d < $distance) {
                $meilleure = $porte;
                $distance = $d;
            }
        }

        return $meilleure;
    }

    /**
     * @param list<GaineDisponible>                 $gaines
     * @param array<string, list<PointCheminement>> $passages
     * @param list<Avertissement>                   $avertissements
     * @param list<Avertissement>                   $erreurs
     */
    private function troncon(ReseauCalcul $reseau, Liaison $liaison, array $gaines, array $passages, array &$avertissements, array &$erreurs): ?Troncon
    {
        $parent = $reseau->elements[$liaison->parent];
        $enfant = $reseau->elements[$liaison->enfant];
        $longueur = $this->distance($parent->point, $enfant->point, $passages);
        if (null === $longueur) {
            $avertissements[] = new Avertissement('electrical_no_door', ['network' => $reseau->nom, 'element' => $enfant->libelle]);

            return null;
        }
        $actifs = $liaison->conducteurs ?? ($parent->type->navette && $enfant->type->navette ? 3 : $enfant->type->conducteursActifs);
        $terre = $enfant->type->terre;
        $total = $actifs + ($terre ? 1 : 0);

        $diametre = null;
        foreach ($gaines as $gaine) {
            if ($gaine->admet($reseau->sectionCentiemes, $total)) {
                $diametre = $gaine->diametre;

                break;
            }
        }
        if (null === $diametre) {
            $erreurs[] = new Avertissement('electrical_no_conduit', ['network' => $reseau->nom, 'element' => $enfant->libelle, 'count' => $total, 'section' => Section::texte($reseau->sectionCentiemes)]);
        }

        $cable = intdiv($longueur * (100 + $reseau->reservePct) + 99, 100) + 2 * self::QUEUE_BOITE_MM;

        return new Troncon($liaison->parent, $liaison->enfant, $longueur, $actifs, $terre, $cable, $diametre);
    }
}
