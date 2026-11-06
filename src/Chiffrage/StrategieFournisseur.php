<?php

declare(strict_types=1);

namespace App\Chiffrage;

use App\Entity\Materiau;
use App\Entity\PrixMateriau;

interface StrategieFournisseur
{
    /**
     * Prix retenu pour un article, `null` si aucun ne convient (ligne « prix manquant »).
     */
    public function choisir(Materiau $materiau, QuantiteBrute $quantite, \DateTimeImmutable $date, Contexte $contexte): ?PrixMateriau;

    /**
     * Choix pour l'ensemble du projet, dans l'ordre des besoins (la livraison rend les choix dépendants entre eux).
     *
     * @param list<Besoin> $besoins
     *
     * @return list<?PrixMateriau>
     */
    public function choisirEnsemble(array $besoins, \DateTimeImmutable $date, Contexte $contexte): array;
}
