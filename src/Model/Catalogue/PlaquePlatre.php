<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 20/02/2026 22:37
 */

declare(strict_types=1);

namespace App\Model\Catalogue;


final class PlaquePlatre implements ContientPrixInterface, LongueurInterface
{
    public const string TYPE_STD='standard';
    public const string TYPE_HYDRO='hydro';

    // en millimètres
    private int $longueur;

    // en millimètres
    private int $largeur;

    private string $type;

    private float $prix = 0;

    //Nombre par lot
    private int $uniteParLot = 1;

    public function getLongueur(): int
    {
        return $this->longueur;
    }

    public function setLongueur(int $longueur): void
    {
        $this->longueur = $longueur;
    }

    public function getLargeur(): int
    {
        return $this->largeur;
    }

    public function setLargeur(int $largeur): void
    {
        $this->largeur = $largeur;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
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
