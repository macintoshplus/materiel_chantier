<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

/**
 * Bande de retour de parement : longueur du côté de l'ouverture, largeur égale à la profondeur du retour (mm).
 * `cote` vaut `linteau`, `cote` ou `appui`.
 */
final readonly class BandeRetour
{
    public function __construct(public string $cote, public int $longueur, public int $largeur)
    {
    }

    /** Surface en mm². */
    public function surface(): int
    {
        return $this->longueur * $this->largeur;
    }
}
