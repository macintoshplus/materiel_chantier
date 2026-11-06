<?php

declare(strict_types=1);

namespace App\Calcul\Substitution;

/**
 * Origine de l'article retenu pour un fournisseur : correspondance exacte, équivalent, ou l'un des deux chez un
 * autre fournisseur.
 */
enum NatureOffre: string
{
    case Exacte = 'exacte';
    case Equivalente = 'equivalente';
    case ExacteAutreFournisseur = 'exacte_autre_fournisseur';
    case EquivalenteAutreFournisseur = 'equivalente_autre_fournisseur';
}
