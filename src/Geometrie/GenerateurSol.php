<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Raccourci de création : polygone au sol -> surface sol. Les sommets reçoivent les identifiants S1..Sn ;
 * les murs et les pans réutilisent ces identifiants (`S1` au sol, `S1h` en haut) pour être reliés.
 */
final class GenerateurSol
{
    public static function idSommet(int $numero): string
    {
        return 'S'.$numero;
    }

    /**
     * @param list<string>|null $ids identifiants existants (même ordre que les points) ; sinon S1..Sn
     *
     * @throws ErreurGeometrique polygon_not_simple
     */
    public function generer(Polygone $sol, ?array $ids = null): SurfaceGeneree
    {
        if (!$sol->estSimple() || !$sol->sensAntiHoraire()) {
            throw new ErreurGeometrique('polygon_not_simple');
        }
        $sommets = [];
        $contraintes = [];
        foreach ($sol->points as $i => $p) {
            $id = $ids[$i] ?? self::idSommet($i + 1);
            $sommets[] = new Sommet($id, $p);
            $contraintes[] = ContrainteSommet::fixe($id, $p->x, $p->y);
        }

        return new SurfaceGeneree(
            'Floor',
            new Plan(new Point3D(0, 0, 0), new Point3D(1000, 0, 0), new Point3D(0, 1000, 0)),
            new ContourIdentifie($sommets),
            $contraintes,
        );
    }
}
