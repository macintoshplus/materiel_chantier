<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Résultat d'un générateur : plan, contour et contraintes d'une surface à créer.
 * `arete` renseigne (mur) l'arête du sol d'origine, par identifiants de sommets du sol.
 */
final readonly class SurfaceGeneree
{
    /**
     * @param list<ContrainteSommet>               $contraintes
     * @param array{debut: string, fin: string}|null $areteSol
     */
    public function __construct(
        public string $nom,
        public Plan $plan,
        public ContourIdentifie $contour,
        public array $contraintes,
        public ?array $areteSol = null,
    ) {
    }

    public function geometrie(): SurfaceGeometrique
    {
        return new SurfaceGeometrique($this->plan, $this->contour);
    }
}
