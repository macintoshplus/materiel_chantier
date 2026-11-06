<?php

declare(strict_types=1);

namespace App\Calcul\Cadre;

/**
 * Zone d'application d'un renfort : tous les montants, ceux qui encadrent une ouverture, ou des positions listées.
 */
enum ZoneRenfort: string
{
    case ToutesPositions = 'toutes_positions';
    case BordsOuvertures = 'bords_ouvertures';
    case PositionsListees = 'positions_listees';
}
