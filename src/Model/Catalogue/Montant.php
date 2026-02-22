<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 20/02/2026 22:26
 */

declare(strict_types=1);

namespace App\Model\Catalogue;


final class Montant implements ContientPrixInterface, LongueurInterface
{
    // en metre
    private float $longueur;

    // en metre
    private float $largeur;

    // en metre
    private float $entraxe;

    private float $prix = 0;

    //Nombre par lot
    private int $uniteParLot = 1;

    public function getLongueur(): float
    {
        return $this->longueur;
    }

    public function setLongueur(float $longueur): void
    {
        $this->longueur = $longueur;
    }

    public function getLargeur(): float
    {
        return $this->largeur;
    }

    public function setLargeur(float $largeur): void
    {
        $this->largeur = $largeur;
    }

    public function getEntraxe(): float
    {
        return $this->entraxe;
    }

    public function setEntraxe(float $entraxe): void
    {
        $this->entraxe = $entraxe;
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
