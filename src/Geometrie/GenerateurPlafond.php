<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Raccourci de création des pans de plafond. Un pan est une surface comme les autres (même modèle) : plan 3D,
 * polygone et contraintes. Les sommets d'égout reprennent les identifiants du haut des murs (`S1h`), les
 * extrémités du faîtage sont `F1` et `F2` ; les jonctions s'en déduisent (ServiceJonctions::detecter).
 */
final class GenerateurPlafond
{
    /**
     * Plafond plat : un pan horizontal. Vu d'en dessous, le polygone est parcouru en sens inverse du sol.
     *
     * @return list<SurfaceGeneree>
     */
    public function plat(ContourIdentifie $sol, int $hauteur): array
    {
        $contraintes = [];
        foreach (array_reverse($sol->sommets) as $s) {
            $contraintes[] = ContrainteSommet::fixe(GenerateurMurs::idHaut($s->id), $s->point->x, -$s->point->y);
        }

        return [new SurfaceGeneree(
            'Ceiling',
            new Plan(new Point3D(0, 0, $hauteur), new Point3D(1000, 0, 0), new Point3D(0, -1000, 0)),
            (new ConstructeurSurface())->construire($contraintes),
            $contraintes,
        )];
    }

    /**
     * Double pente sur un sol rectangulaire (arêtes parallèles aux axes), faîtage à mi-largeur, parallèle à
     * `$axe` ('x' ou 'y'; par défaut le plus grand côté). Deux pans inclinés, et les deux murs pignons
     * (pentagones) qui remplacent les murs rectangulaires perpendiculaires au faîtage.
     *
     * @throws ErreurGeometrique ceiling_unsupported_floor, ceiling_invalid_heights
     */
    public function doublePente(ContourIdentifie $sol, int $hauteurSabliere, int $hauteurFaitage, ?string $axe = null): PlafondGenere
    {
        $polygone = $sol->polygone();
        if (!$polygone->estRectangleAxes() || $hauteurSabliere <= 0 || $hauteurFaitage <= $hauteurSabliere) {
            throw new ErreurGeometrique($polygone->estRectangleAxes() ? 'ceiling_invalid_heights' : 'ceiling_unsupported_floor');
        }
        $boite = $polygone->boiteEnglobante();
        $axe ??= $boite->largeur() >= $boite->hauteur() ? 'x' : 'y';
        // repère local (a le long du faîtage, b perpendiculaire) ; x = a, y = b pour 'x' ; x = -b, y = a pour 'y'
        $monde = static fn (int $a, int $b): Point2D => 'x' === $axe ? new Point2D($a, $b) : new Point2D(-$b, $a);
        $e = 'x' === $axe ? [1, 0] : [0, 1];
        $n = 'x' === $axe ? [0, 1] : [-1, 0];
        $locaux = array_map(static fn (Point2D $p): array => 'x' === $axe ? [$p->x, $p->y] : [$p->y, -$p->x], $polygone->points);
        $local = BoiteEnglobante::depuisPoints(array_map(static fn (array $l): Point2D => new Point2D($l[0], $l[1]), $locaux));
        $a0 = $local->minX;
        $a1 = $local->maxX;
        $b0 = $local->minY;
        $b1 = $local->maxY;
        $bm = $b0 + intdiv($b1 - $b0, 2);
        $largeur = $a1 - $a0;
        $idHaut = static function (int $a, int $b) use ($monde, $sol): string {
            $p = $monde($a, $b);
            foreach ($sol->sommets as $s) {
                if ($s->point->egal($p)) {
                    return GenerateurMurs::idHaut($s->id);
                }
            }
            throw new ErreurGeometrique('ceiling_unsupported_floor');
        };
        $montee = $hauteurFaitage - $hauteurSabliere;

        $pan = function (string $nom, int $a, int $b, int $sens, int $course, string $q0, string $q1, string $q2, string $q3) use ($e, $n, $monde, $largeur, $montee, $hauteurSabliere): SurfaceGeneree {
            $o = $monde($a, $b);
            $longueur = Tolerance::arrondir(hypot($course, $montee));
            // sens = +1 : u = -e et v monte vers +n (pan côté b0) ; sens = -1 : u = +e et v monte vers -n (pan côté b1)
            $u = new Point3D(-$sens * $e[0] * 1000, -$sens * $e[1] * 1000, 0);
            $v = new Point3D($sens * $n[0] * $course, $sens * $n[1] * $course, $montee);
            $contraintes = [
                ContrainteSommet::fixe($q0, 0, 0),
                ContrainteSommet::distance($q1, $q0, 'u', $largeur),
                ContrainteSommet::distance($q2, $q1, 'v', $longueur),
                ContrainteSommet::distance($q3, $q0, 'v', $longueur),
            ];

            return new SurfaceGeneree($nom, new Plan(new Point3D($o->x, $o->y, $hauteurSabliere), $u, $v), (new ConstructeurSurface())->construire($contraintes), $contraintes);
        };
        $pans = [
            $pan('Ceiling pane 1', $a1, $b0, 1, $bm - $b0, $idHaut($a1, $b0), $idHaut($a0, $b0), 'F1', 'F2'),
            $pan('Ceiling pane 2', $a0, $b1, -1, $b1 - $bm, $idHaut($a0, $b1), $idHaut($a1, $b1), 'F2', 'F1'),
        ];

        $pignons = [];
        $gen = new GenerateurMurs();
        foreach ($sol->aretes() as [$debut, $fin]) {
            $pd = $sol->point($debut);
            $pf = $sol->point($fin);
            $localD = 'x' === $axe ? $pd->x : $pd->y;
            $localF = 'x' === $axe ? $pf->x : $pf->y;
            if ($localD !== $localF) {
                continue; // arête parallèle au faîtage : mur rectangulaire d'égout
            }
            $idFaitage = $localD === $a0 ? 'F1' : 'F2';
            $faite = $monde($localD, $bm);
            $mur = $gen->mur($debut, $pd, $fin, $pf, $hauteurSabliere, $hauteurSabliere);
            $apex = new Point2D($pf->distanceA($faite), $hauteurFaitage);
            $contraintes = [
                ...array_slice($mur->contraintes, 0, 3),
                ContrainteSommet::fixe($idFaitage, $apex->x, $apex->y),
                $mur->contraintes[3],
            ];
            $pignons[] = new SurfaceGeneree('', $mur->plan, (new ConstructeurSurface())->construire($contraintes), $contraintes, ['debut' => $debut, 'fin' => $fin]);
        }

        return new PlafondGenere($pans, $pignons);
    }
}
