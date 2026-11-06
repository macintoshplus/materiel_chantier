<?php

declare(strict_types=1);

namespace App\Calcul\Couche;

/**
 * Calcule les besoins d'une couche. Ajouter un type de couche = une classe + le tag `app.calculateur_couche`
 * (posé automatiquement par l'autoconfiguration).
 */
interface CalculateurCouche
{
    /** Code référencé par `EmplacementCouche::$calculateur`. */
    public function code(): string;

    public function calculer(CoucheContexte $couche, ContexteCouches $contexte): ResultatCouche;
}
