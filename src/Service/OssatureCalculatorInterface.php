<?php
/**
 * @copyright Macintoshplus (c) 2026
 * Added by : Macintoshplus at 21/02/2026 23:32
 */

declare(strict_types=1);

namespace App\Service;

use App\Model\Catalogue\Montant;
use App\Model\Catalogue\PlaquePlatre;
use App\Model\Chantier\Appuis;
use App\Model\Chantier\Montant as MontantChantier;
use App\Model\Chantier\PlaquePlatre as PlaquePlatreChantier;
use App\Model\Chantier\Rail;
use App\Model\Mur;

interface OssatureCalculatorInterface
{
    /**
     * @return array{rails: list<Rail>, montants: list<MontantChantier>, plaque_platres: list<PlaquePlatreChantier>, appuis?: list<Appuis>, rail_appuis?: list<MontantChantier>}
     */
    public function calculer(Mur $mur, Montant $montant, PlaquePlatre $plaquePlatre): array;
}
