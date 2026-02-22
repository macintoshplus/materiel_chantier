<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 20/02/2026 21:50
 */

declare(strict_types=1);


namespace App\Model;

use App\Constraints\PositionsIsValid;

#[PositionsIsValid]
class Mur
{
    const string TYPE_DOUBLAGE = 'doublage';
    const string TYPE_CLOISON = 'cloison';

    private float $largeur = 0.0;
    private float $hauteur = 0.0;
    private int $ecartement = 48;

    private int $ecartDebut = 48;
    private int $ecartFin = 48;

    private string $typeStructure = self::TYPE_DOUBLAGE;

    private array $ouvertures = [];

    private array $catalogue = [];

    public function getLargeur(): float
    {
        return $this->largeur;
    }

    public function setLargeur(float $largeur): void
    {
        $this->largeur = $largeur;
    }

    public function getHauteur(): float
    {
        return $this->hauteur;
    }

    public function setHauteur(float $hauteur): void
    {
        $this->hauteur = $hauteur;
    }

    public function getEcartement(): int
    {
        return $this->ecartement;
    }

    public function setEcartement(int $ecartement): void
    {
        $this->ecartement = $ecartement;
    }

    public function getEcartDebut(): int
    {
        return $this->ecartDebut;
    }

    public function getEcartDebutEnMetre(): float
    {
        return $this->ecartDebut /1000;
    }

    public function setEcartDebut(int $ecartDebut): void
    {
        $this->ecartDebut = $ecartDebut;
    }

    public function getEcartFin(): int
    {
        return $this->ecartFin;
    }
    public function getEcartFinEnMetre(): float
    {
        return $this->ecartFin / 1000;
    }

    public function setEcartFin(int $ecartFin): void
    {
        $this->ecartFin = $ecartFin;
    }

    public function getTypeStructure(): string
    {
        return $this->typeStructure;
    }

    public function setTypeStructure(string $typeStructure): void
    {
        $this->typeStructure = $typeStructure;
    }

    public function getOuvertures(): array
    {
        return $this->ouvertures;
    }

    public function addOuverture(array $ouverture): void
    {
        $this->ouvertures[] = $ouverture;
    }

    public function setOuvertures(array $ouvertures): void
    {
        $this->ouvertures = $ouvertures;
    }

    public function getCatalogue(): array
    {
        return $this->catalogue;
    }

    public function setCatalogue(array $catalogue): void
    {
        $this->catalogue = $catalogue;
    }


    public function getSurface(): float
    {
        return $this->largeur * $this->hauteur;
    }

    public function getSurfaceOuvertures(): float
    {
        $surface = 0;
        foreach ($this->ouvertures as $o) {
            $surface += $o['largeur'] * $o['hauteur'];
        }
        return $surface;
    }

    public function getSurfaceNette(): float
    {
        return $this->getSurface() - $this->getSurfaceOuvertures();
    }
}
