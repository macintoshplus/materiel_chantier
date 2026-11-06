<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Nature d'une ligne de chiffrage : `article` (achat agrégé du projet, seul porteur du total), `repartition`
 * (part d'une pièce ou d'une surface dans un achat agrégé, non additive) ou `livraison` (forfait d'un fournisseur).
 */
enum TypeLigneChiffrage: string
{
    case Article = 'article';
    case Repartition = 'repartition';
    case Livraison = 'livraison';
}
