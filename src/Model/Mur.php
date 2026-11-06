<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 20/02/2026 21:50
 */

declare(strict_types=1);


namespace App\Model;

use App\Constraints\PositionsIsValid;
use App\Model\Catalogue\AppuisIntermediaire;
use App\Model\Catalogue\BandeAJointArmee;
use App\Model\Catalogue\BandeAJointPlat;
use App\Model\Catalogue\Montant;
use App\Model\Catalogue\PlaquePlatre;
use App\Model\Catalogue\Rail;

/**
 * @phpstan-type Ouverture array{type: string, largeur: int, hauteur: int, distance_gauche: int, elevation: int, type_pose: string, type_contour: string, refoncement_ouvrant: int}
 * @phpstan-type Catalogue array{montant: Montant, rail: Rail, appuisIntermediaire: AppuisIntermediaire, bandeAJoinPlat: BandeAJointPlat, bandeAJoinArmee: BandeAJointArmee, plaquePlatre: PlaquePlatre}
 *
 * Toutes les longueurs sont en millimètres entiers (surfaces en mm²).
 */
#[PositionsIsValid]
class Mur
{
    const string TYPE_DOUBLAGE = 'doublage';
    const string TYPE_CLOISON = 'cloison';

    private int $largeur = 0;
    private int $hauteur = 0;
    private int $ecartement = 48;

    private int $ecartDebut = 48;
    private int $ecartFin = 48;

    private string $typeStructure = self::TYPE_DOUBLAGE;

    /** @var list<Ouverture> */
    private array $ouvertures = [];

    /** @var Catalogue|array{} */
    private array $catalogue = [];

    public function getLargeur(): int
    {
        return $this->largeur;
    }

    public function setLargeur(int $largeur): void
    {
        $this->largeur = $largeur;
    }

    public function getHauteur(): int
    {
        return $this->hauteur;
    }

    public function setHauteur(int $hauteur): void
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

    public function setEcartDebut(int $ecartDebut): void
    {
        $this->ecartDebut = $ecartDebut;
    }

    public function getEcartFin(): int
    {
        return $this->ecartFin;
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

    /** @return list<Ouverture> */
    public function getOuvertures(): array
    {
        return $this->ouvertures;
    }

    /** @param Ouverture $ouverture */
    public function addOuverture(array $ouverture): void
    {
        $this->ouvertures[] = $ouverture;
    }

    /** @param list<Ouverture> $ouvertures */
    public function setOuvertures(array $ouvertures): void
    {
        $this->ouvertures = $ouvertures;
    }

    /** @return Catalogue|array{} */
    public function getCatalogue(): array
    {
        return $this->catalogue;
    }

    /** @param Catalogue $catalogue */
    public function setCatalogue(array $catalogue): void
    {
        $this->catalogue = $catalogue;
    }


    public function getSurface(): int
    {
        return $this->largeur * $this->hauteur;
    }

    public function getSurfaceOuvertures(): int
    {
        $surface = 0;
        foreach ($this->ouvertures as $o) {
            $surface += $o['largeur'] * $o['hauteur'];
        }
        return $surface;
    }

    public function getSurfaceNette(): int
    {
        return $this->getSurface() - $this->getSurfaceOuvertures();
    }
}
