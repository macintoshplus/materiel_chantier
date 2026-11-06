<?php

declare(strict_types=1);

namespace App\Catalogue\Vue;

use App\Catalogue\MaterielLisible;

/**
 * Lecture d'un isolant. Le calcul de R reste entier : lambda en cent-millièmes de W/m.K (0,035 donne 3500),
 * R en millièmes de m².K/W.
 */
final readonly class IsolantView
{
    private Lecteur $lecteur;

    public function __construct(private MaterielLisible $materiel)
    {
        if ('isolant' !== $materiel->categorie()) {
            throw new \InvalidArgumentException('Material is not an insulation.');
        }
        $this->lecteur = new Lecteur($materiel);
    }

    public function epaisseur(): int
    {
        return $this->lecteur->entier('epaisseur');
    }

    /** Conductivité en 1e-5 W/m.K. */
    public function lambdaCentMillieme(): int
    {
        $l = $this->materiel->valeur('lambda');
        if (!\is_int($l) && !\is_float($l)) {
            throw CaracteristiqueManquante::pour('lambda');
        }

        return (int) round($l * 100000);
    }

    /**
     * Résistance thermique (millièmes de m².K/W) pour une épaisseur donnée : le R certifié s'il existe et
     * que l'épaisseur est celle de l'article, sinon calculé depuis lambda.
     */
    public function resistanceMilli(?int $epaisseurMm = null): int
    {
        $e = $epaisseurMm ?? $this->epaisseur();
        $certifie = $this->materiel->valeur('r_certifie');
        if ((\is_int($certifie) || \is_float($certifie)) && $e === $this->epaisseur()) {
            return (int) round($certifie * 1000);
        }
        $lambda = $this->lambdaCentMillieme();
        if ($lambda <= 0) {
            throw CaracteristiqueManquante::pour('lambda');
        }

        return (int) round($e * 100000 / $lambda);
    }
}
