<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

use App\Calcul\Surface\SurfaceCalculable;
use App\Geometrie\Avertissement;
use App\Geometrie\Direction;

/**
 * Éléments d'ossature d'une surface, en longueurs de pièces (mm entiers, dans le repère de la surface) :
 * rails (ou lisses) bas et haut, montants à l'entraxe, montant final, montants de jambage des ouvertures,
 * montants de raccordement hors grille et rails de linteau / d'appui. Sans Symfony ni Doctrine.
 */
final readonly class PlanOssature
{
    /**
     * @param list<int>           $railsBas
     * @param list<int>           $railsHaut
     * @param list<int>           $montants       grille d'entraxe, montant final et jambages (pièces découpées par les ouvertures)
     * @param list<int>           $raccordements  montants hors grille, un par montant de raccordement
     * @param list<int>           $railsOuvertures
     * @param list<Avertissement> $avertissements
     */
    public function __construct(
        public array $railsBas,
        public array $railsHaut,
        public array $montants,
        public array $raccordements,
        public array $railsOuvertures,
        public int $hauteurMax,
        public array $avertissements = [],
    ) {
    }

    /**
     * @param list<int> $positionsRaccordement positions (mm depuis le bord gauche) des montants de raccordement intermédiaires
     */
    public static function calculer(
        SurfaceCalculable $surface,
        int $entraxe,
        string $extremiteDebut = 'mur',
        string $extremiteFin = 'mur',
        int $montantsRaccordement = 1,
        array $positionsRaccordement = [],
    ): self {
        $boite = $surface->boite();
        $polygone = $surface->polygone();
        $avertissements = [];

        $bas = [];
        $haut = [];
        $sens = $polygone->sensAntiHoraire() ? 1 : -1;
        foreach ($polygone->aretes() as $arete) {
            $dx = ($arete->fin->x - $arete->debut->x) * $sens;
            if (0 === $dx) {
                continue;
            }
            // Contour antihoraire : une arête parcourue vers la droite borde le bas, vers la gauche le haut.
            if ($dx > 0) {
                $bas[] = $arete->longueur();
            } else {
                $haut[] = $arete->longueur();
            }
        }

        $montants = [];
        $lignes = [];
        foreach ($surface->coupes(Direction::Verticale, $entraxe) as $segment) {
            $montants[] = $segment->longueur();
            $lignes[$segment->debut->x] = true;
        }
        $largeur = $boite->largeur();
        if ($largeur > 0 && 0 !== $largeur % $entraxe) {
            array_push($montants, ...self::piecesAu($surface, $boite->maxX));
            $lignes[$boite->maxX] = true;
        }
        $railsOuvertures = [];
        foreach ($surface->ouvertures as $ouverture) {
            $b = $ouverture->forme->boiteEnglobante();
            foreach ([$b->minX, $b->maxX] as $x) {
                if (isset($lignes[$x]) || $x <= $boite->minX || $x > $boite->maxX) {
                    continue;
                }
                array_push($montants, ...self::piecesAu($surface, $x));
                $lignes[$x] = true;
            }
            if ($b->largeur() > 0) {
                $railsOuvertures[] = $b->largeur();
                if ($b->minY > $boite->minY) {
                    $railsOuvertures[] = $b->largeur();
                }
            }
        }

        $raccordements = [];
        foreach ([['debut', $extremiteDebut, $boite->minX], ['fin', $extremiteFin, $boite->maxX]] as [$nom, $type, $x]) {
            if (\in_array($type, ['cloison', 'doublage'], true)) {
                $hauteur = self::hauteurAu($surface, $x);
                for ($i = 0; $i < $montantsRaccordement; ++$i) {
                    $raccordements[] = $hauteur;
                }
            } elseif ('libre' === $type) {
                $avertissements[] = new Avertissement('framing_free_end', ['end' => $nom]);
            }
        }
        foreach ($positionsRaccordement as $position) {
            $x = $boite->minX + $position;
            if ($x <= $boite->minX || $x >= $boite->maxX) {
                continue;
            }
            $hauteur = self::hauteurAu($surface, $x);
            for ($i = 0; $i < $montantsRaccordement; ++$i) {
                $raccordements[] = $hauteur;
            }
        }

        return new self($bas, $haut, $montants, $raccordements, $railsOuvertures, $boite->hauteur(), $avertissements);
    }

    /**
     * Pièces d'un montant posé à l'abscisse `x` (plusieurs si une ouverture le coupe).
     *
     * @return list<int>
     */
    private static function piecesAu(SurfaceCalculable $surface, int $x): array
    {
        $boite = $surface->boite();
        $pas = $x - $boite->minX;
        if ($pas <= 0) {
            $pas = max(1, $boite->largeur());
        }
        $pieces = [];
        foreach ($surface->coupes(Direction::Verticale, $pas) as $segment) {
            if ($segment->debut->x === $x) {
                $pieces[] = $segment->longueur();
            }
        }

        return $pieces;
    }

    private static function hauteurAu(SurfaceCalculable $surface, int $x): int
    {
        return [] === ($pieces = self::piecesAu($surface, $x)) ? 0 : max($pieces);
    }
}
