<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 22/02/2026 00:09
 */

declare(strict_types=1);

namespace App\Model\Catalogue;


final class BandeAJointPlat implements ContientPrixInterface, LongueurInterface
{
    // en metre
    private float $longueur;

    private float $prix = 0;

    //Nombre de mettre par rouleau
    private int $uniteParLot = 1;

    public function getLongueur(): float
    {
        return $this->longueur;
    }

    public function setLongueur(float $longueur): void
    {
        $this->longueur = $longueur;
    }

    public function getPrix(): float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): void
    {
        $this->prix = $prix;
    }

    public function getUniteParLot(): int
    {
        return $this->uniteParLot;
    }

    public function setUniteParLot(int $uniteParLot): void
    {
        $this->uniteParLot = $uniteParLot;
    }
}
