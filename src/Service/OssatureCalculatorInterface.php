<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 21/02/2026 23:32
 */

declare(strict_types=1);

namespace App\Service;

use App\Model\Catalogue\Montant;
use App\Model\Catalogue\PlaquePlatre;
use App\Model\Mur;

interface OssatureCalculatorInterface
{
    public function calculer(Mur $mur, Montant $montant, PlaquePlatre $plaquePlatre): array;
}
