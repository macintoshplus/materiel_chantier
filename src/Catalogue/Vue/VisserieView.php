<?php

declare(strict_types=1);

namespace App\Catalogue\Vue;

use App\Catalogue\MaterielLisible;

final readonly class VisserieView
{
    private Lecteur $lecteur;

    public function __construct(MaterielLisible $materiel)
    {
        if ('visserie' !== $materiel->categorie()) {
            throw new \InvalidArgumentException('Material is not screws.');
        }
        $this->lecteur = new Lecteur($materiel);
    }

    public function fixable(): PlageFixable
    {
        return new PlageFixable(
            $this->lecteur->entier('pose.epaisseur_fixable_min'),
            $this->lecteur->entier('pose.epaisseur_fixable_max'),
        );
    }
}
