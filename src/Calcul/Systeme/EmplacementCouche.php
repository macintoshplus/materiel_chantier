<?php

declare(strict_types=1);

namespace App\Calcul\Systeme;

use App\Calcul\Couche\PositionCouche;

/**
 * Place prévue par une définition de système pour une ou plusieurs couches d'un même rôle.
 */
final readonly class EmplacementCouche
{
    /**
     * @param string              $code        identifiant dans la définition (stocké sur `CoucheAppliquee`)
     * @param string              $categorie   code de la catégorie de matériau attendue
     * @param list<PositionCouche> $positions  positions autorisées ; vide = la position n'a pas de sens (attache, ossature)
     * @param list<int>           $orientations orientations autorisées (degrés, relatives à l'ossature)
     * @param string              $calculateur code du `CalculateurCouche`
     */
    public function __construct(
        public string $code,
        public string $role,
        public int $min,
        public int $max,
        public string $categorie,
        public string $calculateur,
        public array $positions = [],
        public array $orientations = [0],
    ) {
        if ($min < 0 || $max < $min) {
            throw new \InvalidArgumentException('Invalid cardinality.');
        }
    }

    public function autorisePosition(?PositionCouche $position): bool
    {
        if ([] === $this->positions) {
            return true;
        }

        return null !== $position && \in_array($position, $this->positions, true);
    }
}
