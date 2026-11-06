<?php

declare(strict_types=1);

namespace App\Catalogue\Vue;

use App\Catalogue\MaterielLisible;

final readonly class PlaqueView
{
    private Lecteur $lecteur;

    public function __construct(MaterielLisible $materiel)
    {
        if ('plaque_platre' !== $materiel->categorie()) {
            throw new \InvalidArgumentException('Material is not a plasterboard.');
        }
        $this->lecteur = new Lecteur($materiel);
    }

    public function epaisseur(): int
    {
        return $this->lecteur->entier('epaisseur');
    }

    public function largeur(): int
    {
        return $this->lecteur->entier('largeur');
    }

    public function longueur(): int
    {
        return $this->lecteur->entier('longueur');
    }

    public function pose(): PoseePlaque
    {
        return new PoseePlaque(
            $this->lecteur->entier('pose.entraxe_max'),
            $this->lecteur->entier('pose.pas_vis_premiere_peau'),
            $this->lecteur->entier('pose.pas_vis_derniere_peau'),
            $this->lecteur->entier('pose.pas_vis_peau_unique'),
        );
    }
}
