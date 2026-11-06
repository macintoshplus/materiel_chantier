<?php

declare(strict_types=1);

namespace App\Service;

use App\Calcul\Electricite\ResultatCheminement;
use App\Entity\Equipement;
use App\Entity\Reseau;
use App\Geometrie\Avertissement;

/**
 * Un réseau tel que l'écran des circuits l'affiche : éléments, charge, résultat du cheminement (null tant que le calcul
 * n'en produit pas : aucun élément, famille non calculée), quantités annexes et messages du réseau.
 */
final readonly class ResumeReseau
{
    /**
     * @param list<Equipement>      $equipements
     * @param list<array{categorie: string, criteres: string, quantite: int, longueur: bool}> $quantites catégorie (libellé à traduire), critères lisibles, nombre ou longueur en mm
     * @param list<Avertissement>   $avertissements
     * @param list<Avertissement>   $erreurs
     */
    public function __construct(
        public Reseau $reseau,
        public array $equipements,
        public ?Equipement $depart,
        public int $charge,
        public int $chargeMax,
        public ?ResultatCheminement $cheminement,
        public array $quantites,
        public array $avertissements,
        public array $erreurs,
    ) {
    }
}
