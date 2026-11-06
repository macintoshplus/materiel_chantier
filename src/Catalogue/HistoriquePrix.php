<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Entity\PrixMateriau;

/**
 * Règles de l'historique des prix d'un couple (matériau, fournisseur), sans accès à la base.
 */
final class HistoriquePrix
{
    /**
     * @param iterable<PrixMateriau> $prix prix du couple
     */
    public function prixA(iterable $prix, \DateTimeImmutable $date): ?PrixMateriau
    {
        foreach ($prix as $p) {
            if ($p->estValideA($date)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Ajoute `$nouveau` au couple : clôt le prix courant à la date de début du nouveau.
     *
     * @param iterable<PrixMateriau> $existants prix du couple, hors `$nouveau`
     *
     * @throws \InvalidArgumentException si le nouveau prix débute avant le prix courant
     */
    public function definir(iterable $existants, PrixMateriau $nouveau): void
    {
        foreach ($existants as $p) {
            if ($p->estCourant()) {
                $p->cloturer($nouveau->getDateDebut());
            }
        }
    }
}
