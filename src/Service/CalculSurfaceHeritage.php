<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Surface;
use App\Model\Mur;

/**
 * Adaptateur Surface vers les calculateurs historiques (cloison et doublage) : mêmes résultats que l'écran
 * `/doublage/mur` pour un rectangle aligné sur les axes.
 */
final class CalculSurfaceHeritage
{
    public function __construct(
        private readonly AdaptateurSurface $adaptateur,
        private readonly OssatureCloisonCalculator $cloison,
        private readonly OssatureDoublageCalculator $doublage,
        private readonly MateriauxCalculator $materiaux,
        private readonly CatalogueHeritage $catalogue,
    ) {
    }

    /**
     * @param string $typeStructure `Mur::TYPE_DOUBLAGE` ou `Mur::TYPE_CLOISON`
     *
     * @return array{ossature: array<string, mixed>, optimisation: array<int|string, array<string, mixed>>}
     */
    public function calculer(Surface $surface, string $typeStructure, \DateTimeImmutable $date): array
    {
        $mur = $this->adaptateur->versMur($surface, $typeStructure);

        return $this->calculerMur($mur, $date);
    }

    /**
     * @return array{ossature: array<string, mixed>, optimisation: array<int|string, array<string, mixed>>}
     */
    public function calculerMur(Mur $mur, \DateTimeImmutable $date): array
    {
        $catalogue = $this->catalogue->charger($date);
        $montant = $catalogue['montant'];
        $plaque = $catalogue['plaquePlatre'];

        $ossature = Mur::TYPE_CLOISON === $mur->getTypeStructure()
            ? $this->cloison->calculer($mur, $montant, $plaque)
            : $this->doublage->calculer($mur, $montant, $plaque);

        $optimisation = $this->materiaux->calculer(
            appuis: $ossature['appuis'] ?? [],
            montants: array_merge($ossature['montants'], $ossature['rail_appuis'] ?? []),
            rails: $ossature['rails'],
            plaquePlatres: $ossature['plaque_platres'],
            longueursStandard: [
                'montant' => $montant,
                'rail' => $catalogue['rail'],
                'plaque-platre' => $plaque,
                'joint-armee' => $catalogue['bandeAJoinArmee'],
                'joint-plat' => $catalogue['bandeAJoinPlat'],
            ],
            appuisIntermediaire: $catalogue['appuisIntermediaire'],
        );

        return ['ossature' => $ossature, 'optimisation' => $optimisation];
    }
}
