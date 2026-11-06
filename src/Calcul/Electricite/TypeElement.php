<?php

declare(strict_types=1);

namespace App\Calcul\Electricite;

/**
 * Type d'élément électrique du catalogue (donnée, pas du code : voir `RegistreTypesElements`).
 *
 * `conducteursActifs` est le nombre de conducteurs actifs (hors terre) du tronçon qui arrive à l'élément ; un tronçon
 * entre deux commandes « navette » (va-et-vient, permutateur) en compte 3. `connecteurs` est le nombre de connecteurs
 * de dérivation prévus par conducteur du tronçon entrant (0 pour un élément qui n'est pas une dérivation).
 */
final readonly class TypeElement
{
    public const string FAMILLE_DISTRIBUTION = 'distribution';
    public const string FAMILLE_COMMANDE = 'commande';
    public const string FAMILLE_PRISE = 'prise';
    public const string FAMILLE_LUMINAIRE = 'luminaire';
    public const string FAMILLE_CHARGE = 'charge';

    public function __construct(
        public string $code,
        public string $libelle,
        public string $famille,
        public int $conducteursActifs = 2,
        public bool $terre = true,
        public bool $navette = false,
        public int $hauteurDefautMm = 0,
        public bool $derivation = false,
        public ?MaterielAssocie $boite = null,
        public ?MaterielAssocie $mecanisme = null,
        public ?MaterielAssocie $plaque = null,
        public ?MaterielAssocie $connecteur = null,
    ) {
    }
}
