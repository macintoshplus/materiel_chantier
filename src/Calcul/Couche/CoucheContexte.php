<?php

declare(strict_types=1);

namespace App\Calcul\Couche;

use App\Calcul\Surface\SurfaceCalculable;
use App\Calcul\Systeme\EmplacementCouche;
use App\Catalogue\MaterielLisible;
use App\Geometrie\Direction;

/**
 * Ce que reçoit un calculateur pour une couche : la couche décrite, son emplacement, le matériau retenu
 * (épinglé ou résolu), la surface et les paramètres du système.
 */
final readonly class CoucheContexte
{
    /**
     * @param array<string, scalar|null> $parametres
     */
    public function __construct(
        public CoucheDecrite $couche,
        public EmplacementCouche $emplacement,
        public ?MaterielLisible $materiau,
        public SurfaceCalculable $surface,
        public array $parametres = [],
    ) {
    }

    public function epaisseur(): ?int
    {
        if (null !== $this->couche->epaisseurOverride) {
            return $this->couche->epaisseurOverride;
        }
        $e = $this->materiau?->valeur('epaisseur');

        return \is_int($e) ? $e : null;
    }

    /**
     * Direction des coupes de la couche : l'orientation est relative à la direction de l'ossature de la surface.
     */
    public function directionCoupes(ContexteCouches $contexte): Direction
    {
        return 90 === $this->couche->orientation % 180 ? $contexte->directionOssature->perpendiculaire() : $contexte->directionOssature;
    }

    public function parametreEntier(string $nom, int $defaut): int
    {
        $v = $this->parametres[$nom] ?? null;

        return \is_int($v) ? $v : $defaut;
    }
}
