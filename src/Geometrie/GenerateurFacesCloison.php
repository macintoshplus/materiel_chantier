<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Deux faces (A à gauche du tracé, B à droite) d'une cloison : tracé + épaisseur + plafond de la pièce.
 *
 * Repères : la face B est vue dans le sens du tracé (`u` = distance depuis le début) ; la face A est inversée
 * (`u` = distance depuis la fin), comme un mur dont l'origine est au sommet `fin`. Les sommets portent les
 * mêmes identifiants sur les deux faces : `db`, `fb` (bas, début / fin), `dh`, `fh` (haut) et `r1h`… (ruptures).
 */
final class GenerateurFacesCloison
{
    /** Épaisseur provisoire (mm) tant que la cloison n'a pas de système (étape 05). */
    public const EPAISSEUR_PROVISOIRE = 100;

    /**
     * @throws ErreurGeometrique partition_degenerate, partition_no_ceiling
     */
    public function generer(TraceCloison $trace, ?int $epaisseur, HauteurPlafond $plafond): FacesCloison
    {
        $epaisseur ??= self::EPAISSEUR_PROVISOIRE;
        $longueur = $trace->longueur();
        if ($longueur <= 0 || $epaisseur <= 0) {
            throw new ErreurGeometrique('partition_degenerate');
        }
        $segment = $trace->segment();
        $hauts = [['dh', 0, $this->hauteur($plafond, $trace->debut)]];
        foreach ($plafond->ruptures($segment) as $i => $p) {
            $hauts[] = ['r'.($i + 1).'h', $segment->positionDe($p), $this->hauteur($plafond, $p)];
        }
        $hauts[] = ['fh', $longueur, $this->hauteur($plafond, $trace->fin)];

        // face B : u = distance depuis le début
        $b = [new Sommet('db', new Point2D(0, 0)), new Sommet('fb', new Point2D($longueur, 0))];
        foreach (array_reverse($hauts) as [$id, $s, $h]) {
            $b[] = new Sommet($id, new Point2D($s, $h));
        }
        // face A : u = distance depuis la fin
        $a = [new Sommet('fb', new Point2D(0, 0)), new Sommet('db', new Point2D($longueur, 0))];
        foreach ($hauts as [$id, $s, $h]) {
            $a[] = new Sommet($id, new Point2D($longueur - $s, $h));
        }

        $n = $trace->normaleGauche();
        $dx = Tolerance::arrondir($n[0] * $epaisseur / 2);
        $dy = Tolerance::arrondir($n[1] * $epaisseur / 2);
        $v = $trace->fin->moins($trace->debut);
        $planA = new Plan(new Point3D($trace->fin->x + $dx, $trace->fin->y + $dy, 0), new Point3D(-$v->x, -$v->y, 0), new Point3D(0, 0, 1000));
        $planB = new Plan(new Point3D($trace->debut->x - $dx, $trace->debut->y - $dy, 0), new Point3D($v->x, $v->y, 0), new Point3D(0, 0, 1000));

        return new FacesCloison(
            self::face('Side A', $planA, $a),
            self::face('Side B', $planB, $b),
            $epaisseur,
        );
    }

    private function hauteur(HauteurPlafond $plafond, Point2D $p): int
    {
        $h = $plafond->hauteurA($p);
        if (null === $h) {
            throw new ErreurGeometrique('partition_no_ceiling');
        }
        if ($h <= 0) {
            throw new ErreurGeometrique('partition_degenerate');
        }

        return $h;
    }

    /**
     * @param list<Sommet> $sommets
     */
    private static function face(string $nom, Plan $plan, array $sommets): SurfaceGeneree
    {
        $contraintes = array_map(static fn (Sommet $s): ContrainteSommet => ContrainteSommet::fixe($s->id, $s->point->x, $s->point->y), $sommets);

        return new SurfaceGeneree($nom, $plan, new ContourIdentifie($sommets), $contraintes);
    }
}
