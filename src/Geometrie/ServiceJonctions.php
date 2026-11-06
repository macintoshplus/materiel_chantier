<?php

declare(strict_types=1);

namespace App\Geometrie;

use App\Enum\TypeJonction;

/**
 * Jonctions entre surfaces : détection des arêtes partagées (mêmes identifiants de sommets), type d'angle
 * déduit de la géométrie 3D, égalité des longueurs (sauf `cloison_sur_*`, qui s'appuie sur une portion).
 */
final class ServiceJonctions
{
    /**
     * @param array<string, SurfaceGeometrique> $surfaces
     *
     * @return list<JonctionDecrite>
     */
    public function detecter(array $surfaces): array
    {
        $cles = array_keys($surfaces);
        $jonctions = [];
        foreach ($cles as $i => $cleA) {
            foreach (\array_slice($cles, $i + 1) as $cleB) {
                $a = $surfaces[$cleA];
                $b = $surfaces[$cleB];
                foreach ($a->contour->aretes() as [$d, $f]) {
                    foreach ($b->contour->aretes() as [$bd, $bf]) {
                        if (!(($d === $bd && $f === $bf) || ($d === $bf && $f === $bd))) {
                            continue;
                        }
                        $jonctions[] = new JonctionDecrite(
                            (string) $cleA, $d, $f, (string) $cleB, $bd, $bf,
                            $this->typeAngle($a, $b, $d, $f),
                            $a->longueurAreteReelle($d, $f),
                            $b->longueurAreteReelle($bd, $bf),
                        );
                    }
                }
            }
        }

        return $jonctions;
    }

    /**
     * Les longueurs réelles des deux arêtes jointes doivent être égales (± 1 mm), sauf pour les jonctions de cloison.
     *
     * @param list<JonctionDecrite> $jonctions
     *
     * @return list<Avertissement>
     */
    public function verifier(array $jonctions): array
    {
        $avertissements = [];
        foreach ($jonctions as $j) {
            if ($j->type->exigeEgaliteLongueurs() && !Tolerance::egal($j->longueurA, $j->longueurB)) {
                $avertissements[] = new Avertissement('junction_length_mismatch', [
                    'a' => $j->cleA, 'b' => $j->cleB, 'lengthA' => $j->longueurA, 'lengthB' => $j->longueurB,
                ]);
            }
        }

        return $avertissements;
    }

    /**
     * Type d'angle de l'arête `debut` -> `fin` (identifiants de A) entre A et B.
     */
    public function typeAngle(SurfaceGeometrique $a, SurfaceGeometrique $b, string $debut, string $fin): TypeJonction
    {
        $p = $a->pointPiece($debut);
        $q = $a->pointPiece($fin);
        $arete = [$q->x - $p->x, $q->y - $p->y, $q->z - $p->z];
        $norme = sqrt($arete[0] ** 2 + $arete[1] ** 2 + $arete[2] ** 2);
        if (0.0 === $norme) {
            return TypeJonction::Plat;
        }
        $arete = [$arete[0] / $norme, $arete[1] / $norme, $arete[2] / $norme];
        // direction de B, depuis l'arête vers l'intérieur de B
        $c = $b->plan->versPiece($b->polygone()->moyenne());
        $d = [$c->x - $p->x, $c->y - $p->y, $c->z - $p->z];
        $s = $d[0] * $arete[0] + $d[1] * $arete[1] + $d[2] * $arete[2];
        $d = [$d[0] - $s * $arete[0], $d[1] - $s * $arete[1], $d[2] - $s * $arete[2]];
        $nd = sqrt($d[0] ** 2 + $d[1] ** 2 + $d[2] ** 2);
        if (0.0 === $nd) {
            return TypeJonction::Plat;
        }
        $na = $a->plan->normale();
        $produit = ($d[0] * $na[0] + $d[1] * $na[1] + $d[2] * $na[2]) / $nd;

        $incline = static fn (Plan $plan): bool => !$plan->estHorizontal() && !$plan->estVertical();
        $saillant = $produit > 0.001;
        $rentrant = $produit < -0.001;
        if ($incline($a->plan) && $incline($b->plan)) {
            return $rentrant ? TypeJonction::Noue : ($saillant ? TypeJonction::Faitage : TypeJonction::Plat);
        }
        $horizontale = abs($arete[2]) < 0.001;
        if ($horizontale && ($incline($a->plan) && $b->plan->estVertical() || $incline($b->plan) && $a->plan->estVertical())) {
            return TypeJonction::Sabliere;
        }

        return $rentrant ? TypeJonction::AngleRentrant : ($saillant ? TypeJonction::AngleSaillant : TypeJonction::Plat);
    }
}
