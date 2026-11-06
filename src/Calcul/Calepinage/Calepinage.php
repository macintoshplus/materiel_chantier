<?php

declare(strict_types=1);

namespace App\Calcul\Calepinage;

final readonly class Calepinage
{
    /**
     * @param list<PlaqueCalepinee> $plaques
     */
    public function __construct(
        public array $plaques,
        public int $largeurZone,
        public int $hauteurZone,
        public int $largeurPlaque,
        public int $hauteurPlaque,
        public int $decalage,
    ) {
    }

    /**
     * Abscisses (mm) des joints verticaux entre colonnes, bords de la zone exclus.
     *
     * @return list<int>
     */
    public function jointsVerticaux(): array
    {
        $joints = [];
        foreach ($this->plaques as $plaque) {
            foreach ([$plaque->x, $plaque->x + $plaque->largeur] as $x) {
                if ($x > 0 && $x < $this->largeurZone) {
                    $joints[$x] = $x;
                }
            }
        }
        sort($joints);

        return $joints;
    }

    /**
     * Plus petit écart (mm) entre un joint vertical de ce calepinage et le joint vertical le plus proche de l'autre ;
     * `null` si l'un des deux n'a aucun joint.
     */
    public function ecartMinimalJoints(self $autre): ?int
    {
        $miens = $this->jointsVerticaux();
        $siens = $autre->jointsVerticaux();
        if ([] === $miens || [] === $siens) {
            return null;
        }
        $min = PHP_INT_MAX;
        foreach ($miens as $a) {
            foreach ($siens as $b) {
                $min = min($min, abs($a - $b));
            }
        }

        return $min;
    }

    public function surfacePlaques(): int
    {
        $total = 0;
        foreach ($this->plaques as $plaque) {
            $total += $plaque->largeur * $plaque->hauteur;
        }

        return $total;
    }
}
