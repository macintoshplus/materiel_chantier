<?php

declare(strict_types=1);

namespace App\Calcul\Calepinage;

use App\Geometrie\Avertissement;

/**
 * Composant partagé de calepinage de plaques en grille (plaques entières, chutes en bord de zone).
 * Le décalage translate les colonnes le long de `u` : la première colonne est une chute de la largeur du décalage,
 * les joints verticaux tombent à `décalage + k × largeur de plaque`.
 */
final class CalepinagePlaques
{
    /** Écart minimal (mm) entre les joints verticaux de deux peaux successives. */
    public const int ECART_MIN_JOINTS = 400;

    public function calepiner(int $largeurZone, int $hauteurZone, int $largeurPlaque, int $hauteurPlaque, int $decalage = 0): Calepinage
    {
        if ($largeurPlaque <= 0 || $hauteurPlaque <= 0) {
            throw new \InvalidArgumentException('The board dimensions must be positive.');
        }
        $decalage = ($decalage % $largeurPlaque + $largeurPlaque) % $largeurPlaque;

        $bornesX = [0];
        $x = 0 === $decalage ? $largeurPlaque : $decalage;
        while ($x < $largeurZone) {
            $bornesX[] = $x;
            $x += $largeurPlaque;
        }
        $bornesX[] = $largeurZone;
        $bornesY = [];
        for ($y = 0; $y < $hauteurZone; $y += $hauteurPlaque) {
            $bornesY[] = $y;
        }
        $bornesY[] = $hauteurZone;

        $plaques = [];
        if ($largeurZone > 0 && $hauteurZone > 0) {
            for ($r = 0; $r < \count($bornesY) - 1; ++$r) {
                for ($c = 0; $c < \count($bornesX) - 1; ++$c) {
                    $plaques[] = new PlaqueCalepinee(
                        $bornesX[$c],
                        $bornesY[$r],
                        $bornesX[$c + 1] - $bornesX[$c],
                        $bornesY[$r + 1] - $bornesY[$r],
                        $c,
                        $r,
                    );
                }
            }
        }

        return new Calepinage($plaques, $largeurZone, $hauteurZone, $largeurPlaque, $hauteurPlaque, $decalage);
    }

    /**
     * v1 : décalage fixe d'une demi-plaque.
     */
    public function decalageDemiPlaque(int $largeurPlaque): int
    {
        return intdiv($largeurPlaque, 2);
    }

    /**
     * Avertissement si les joints de la peau courante sont trop proches de ceux de la précédente.
     */
    public function verifierDecalage(Calepinage $precedent, Calepinage $courant): ?Avertissement
    {
        $ecart = $precedent->ecartMinimalJoints($courant);
        if (null !== $ecart && $ecart < self::ECART_MIN_JOINTS) {
            return new Avertissement('layer_joints_too_close', ['actual' => $ecart, 'minimum' => self::ECART_MIN_JOINTS]);
        }

        return null;
    }
}
