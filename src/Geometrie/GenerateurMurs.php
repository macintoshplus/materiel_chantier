<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Raccourci de création : une surface murale par arête du sol. Chaque mur est un polygone défini par des
 * contraintes (largeur = distance, hauteurs = distances) : la source de vérité reste le polygone et ses contraintes.
 *
 * Repère d'un mur : origine au sommet `fin` de l'arête (au sol), `u` vers le sommet `début`, `v` vertical ;
 * le sol étant anti-horaire, `u x v` pointe vers l'intérieur de la pièce.
 */
final class GenerateurMurs
{
    public static function idHaut(string $idSol): string
    {
        return $idSol.'h';
    }

    /**
     * @return list<SurfaceGeneree>
     */
    public function generer(ContourIdentifie $sol, int $hauteur): array
    {
        if ($hauteur <= 0) {
            throw new ErreurGeometrique('constraint_invalid_value', ['vertex' => 'height'], 'height');
        }
        $murs = [];
        foreach ($sol->aretes() as $i => [$debut, $fin]) {
            $mur = $this->mur($debut, $sol->point($debut), $fin, $sol->point($fin), $hauteur, $hauteur);
            $murs[] = new SurfaceGeneree('Wall '.($i + 1), $mur->plan, $mur->contour, $mur->contraintes, $mur->areteSol);
        }

        return $murs;
    }

    /**
     * Mur (trapèze si les hauteurs diffèrent) sur l'arête `debut` -> `fin` du sol.
     */
    public function mur(string $idDebut, Point2D $debut, string $idFin, Point2D $fin, int $hauteurDebut, int $hauteurFin): SurfaceGeneree
    {
        $largeur = $debut->distanceA($fin);
        $plan = new Plan(
            new Point3D($fin->x, $fin->y, 0),
            new Point3D($debut->x - $fin->x, $debut->y - $fin->y, 0),
            new Point3D(0, 0, 1000),
        );
        $contraintes = [
            ContrainteSommet::fixe($idFin, 0, 0),
            ContrainteSommet::distance($idDebut, $idFin, 'u', $largeur),
            ContrainteSommet::distance(self::idHaut($idDebut), $idDebut, 'v', $hauteurDebut),
            ContrainteSommet::distance(self::idHaut($idFin), $idFin, 'v', $hauteurFin),
        ];

        return new SurfaceGeneree('', $plan, (new ConstructeurSurface())->construire($contraintes), $contraintes, ['debut' => $idDebut, 'fin' => $idFin]);
    }
}
