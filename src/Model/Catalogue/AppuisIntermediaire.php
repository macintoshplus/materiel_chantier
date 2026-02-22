<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 22/02/2026 00:09
 */

declare(strict_types=1);

namespace App\Model\Catalogue;


final class AppuisIntermediaire implements ContientPrixInterface
{


    private float $prix = 0;

    //Nombre par lot
    private int $uniteParLot = 1;

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
